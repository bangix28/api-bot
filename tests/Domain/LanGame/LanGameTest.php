<?php

namespace App\Tests\Domain\LanGame;

use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanGame\GameSnapshot;
use App\Domain\LanGame\LanGame;
use App\Domain\LanGame\LanGameStatus;
use PHPUnit\Framework\TestCase;

class LanGameTest extends TestCase
{
    private const int LAN = 7;

    private static function receivedAt(string $time = '21:00:00'): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-07 ' . $time);
    }

    private static function openedAt(float $gameTimeSeconds): LanGame
    {
        $snapshot = GameSnapshotBuilder::aGame()->at($gameTimeSeconds)->build();

        return LanGame::open(LanGame::idFor(self::LAN, $snapshot, self::receivedAt()), self::LAN, LanQueue::ARAM_MAYHEM, $snapshot, self::receivedAt());
    }

    public function testLeDebutEstEstimeDepuisLaReceptionEtLeTempsDeJeu(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->at(75.9)->build();

        $this->assertEquals(self::receivedAt('20:58:45'), LanGame::estimatedStart($snapshot, self::receivedAt()));
    }

    public function testLIdentifiantDependDeLaLanDuDebutEtDesJoueurs(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->at(30)->build();
        $gameId = LanGame::idFor(self::LAN, $snapshot, self::receivedAt());

        $this->assertMatchesRegularExpression('/^lan7-\d+-[0-9a-f]{12}$/', $gameId);
        $this->assertSame($gameId, LanGame::idFor(self::LAN, GameSnapshotBuilder::aGame()->seenBy('Charlie#EUW')->at(30)->build(), self::receivedAt()), 'Un autre poste de la même partie.');
        $this->assertNotSame($gameId, LanGame::idFor(8, $snapshot, self::receivedAt()));
        $this->assertNotSame($gameId, LanGame::idFor(self::LAN, $snapshot, self::receivedAt('21:00:01')));
        $this->assertNotSame($gameId, LanGame::idFor(self::LAN, GameSnapshotBuilder::aGame()->replacing('Inconnu1#EUW', 'Autre#EUW')->at(30)->build(), self::receivedAt()));
    }

    public function testUnePartieOuverteDansLesTempsEngageLesCartes(): void
    {
        $game = self::openedAt(LanGame::LATE_START_SECONDS);

        $this->assertSame(LanGameStatus::IN_PROGRESS, $game->status);
        $this->assertTrue($game->engagesCards());
        $this->assertSame(LanQueue::ARAM_MAYHEM, $game->queue);
        $this->assertEquals(self::receivedAt(), $game->lastReportAt);
        $this->assertNull($game->settledScores);
    }

    public function testUnePartieRejointeApres90SecondesNEngageAucuneCarte(): void
    {
        $game = self::openedAt(LanGame::LATE_START_SECONDS + 0.5);

        $this->assertSame(LanGameStatus::JOINED_TOO_LATE, $game->status);
        $this->assertFalse($game->engagesCards());
    }

    public function testUnRelevePosterieurDeLaMemePartieLaRetrouve(): void
    {
        $game = self::openedAt(30);
        $later = GameSnapshotBuilder::aGame()->seenBy('Bravo#EUW')->at(330)->build();

        $this->assertTrue($game->matches($later, self::receivedAt('21:05:00')));
        $this->assertTrue($game->matches($later, self::receivedAt('21:06:00')), 'Une minute d\'écart tolérée.');
        $this->assertFalse($game->matches($later, self::receivedAt('21:06:01')));
        $this->assertFalse($game->matches($later, self::receivedAt('21:03:59')));
    }

    public function testUneAutrePartieNeCorrespondPas(): void
    {
        $game = self::openedAt(30);

        $this->assertFalse($game->matches(GameSnapshotBuilder::aGame()->inMode('ARAM')->at(30)->build(), self::receivedAt()));
        $this->assertFalse($game->matches(GameSnapshotBuilder::aGame()->inQueue(LanQueue::RANKED_FLEX)->at(30)->build(), self::receivedAt()));
        $this->assertTrue($game->matches(GameSnapshotBuilder::aGame()->inQueue(LanQueue::ARAM_MAYHEM)->at(30)->build(), self::receivedAt()), 'Avec ou sans file lue, c\'est la même ARAM du chaos.');
        $this->assertFalse($game->matches(GameSnapshotBuilder::aGame()->replacing('Inconnu1#EUW', 'Autre#EUW')->at(30)->build(), self::receivedAt()));
    }

    public function testRegler(): void
    {
        $final = GameSnapshotBuilder::aGame()->withScore('Bravo#EUW', 4, 2, 7)->withScore('Alpha#EUW', 1, 0, 3)->build();

        $game = self::openedAt(30)->withReportAt(self::receivedAt('21:20:00'))->settle($final);

        $this->assertSame(LanGameStatus::SETTLED, $game->status);
        $this->assertEquals(self::receivedAt('21:20:00'), $game->lastReportAt);
        $this->assertTrue($game->agreesWith($final));
        $this->assertTrue($game->agreesWith(GameSnapshotBuilder::aGame()->seenBy('Charlie#EUW')->withScore('Alpha#EUW', 1, 0, 3)->withScore('Bravo#EUW', 4, 2, 7)->build()));
        $this->assertFalse($game->agreesWith(GameSnapshotBuilder::aGame()->withScore('Bravo#EUW', 4, 2, 8)->withScore('Alpha#EUW', 1, 0, 3)->build()));
    }

    public function testLeRecoupementNeDependNiDeLOrdreNiDeLaCasseDesJoueurs(): void
    {
        $final = GameSnapshotBuilder::aGame()->withScore('Bravo#EUW', 4, 2, 7)->build();
        $game = self::openedAt(30)->settle($final);

        $reordered = new GameSnapshot($final->gameMode, $final->gameTimeSeconds, $final->activeRiotId, array_reverse($final->players), []);
        $otherCase = GameSnapshotBuilder::aGame()->replacing('Inconnu1#EUW', 'INCONNU1#euw')->withScore('Bravo#EUW', 4, 2, 7)->build();

        $this->assertTrue($game->agreesWith($reordered));
        $this->assertTrue($game->agreesWith($otherCase));
    }

    public function testOnNeRegleQuUnePartieEnCours(): void
    {
        $this->expectException(\LogicException::class);

        self::openedAt(120)->settle(GameSnapshotBuilder::aGame()->build());
    }

    public function testAbandonApresUneHeureSansReleve(): void
    {
        $game = self::openedAt(30);

        $this->assertFalse($game->isAbandonedAt(self::receivedAt('22:00:00')));
        $this->assertTrue($game->isAbandonedAt(self::receivedAt('22:00:01')));
        $this->assertFalse($game->withReportAt(self::receivedAt('21:30:00'))->isAbandonedAt(self::receivedAt('22:00:01')));
        $this->assertSame(LanGameStatus::ABANDONED, $game->abandon()->status);
    }

    public function testSeuleUnePartieEnCoursPeutEtreAbandonnee(): void
    {
        $settled = self::openedAt(30)->settle(GameSnapshotBuilder::aGame()->build());

        $this->assertFalse($settled->isAbandonedAt(self::receivedAt('23:00:00')));
        $this->assertFalse(self::openedAt(120)->isAbandonedAt(self::receivedAt('23:00:00')));

        $this->expectException(\LogicException::class);
        $settled->abandon();
    }
}
