<?php

namespace App\Tests\Application\LanGame\ReceiveReport;

use App\Application\LanGame\ReceiveReport\IgnoredReason;
use App\Domain\Agent\AgentToken;
use App\Domain\Agent\AgentTokenExpiredException;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\Verdict;
use App\Domain\LanGame\Event\GameEnd;
use App\Domain\LanGame\Event\Multikill;
use App\Domain\LanGame\LanGameStatus;
use App\Domain\LanGame\NotYourAccountException;
use App\Domain\LanGame\ReportTooFrequentException;
use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\TrackedRiotAccount;
use App\Tests\Application\LanChallenge\LanChallengeFixture;
use App\Tests\Application\LanGame\LanGameFixture;
use App\Tests\Domain\LanGame\GameSnapshotBuilder;
use PHPUnit\Framework\TestCase;

class ReceiveReportHandlerTest extends TestCase
{
    private LanGameFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new LanGameFixture();
    }

    /** Fin de partie : Alpha a fait un doublé et son équipe a gagné. */
    private static function finalSnapshot(int $alphaKills = 2): GameSnapshotBuilder
    {
        return GameSnapshotBuilder::aGame()
            ->at(1000)
            ->withScore('Alpha#EUW', $alphaKills, 1)
            ->withEvents(new Multikill('Alpha', 2), new GameEnd(true));
    }

    /** Une partie ouverte par Alpha à 21:00:00 (30 s de jeu), puis son relevé final à 21:16:10. */
    private function playAGame(): void
    {
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
        $this->fixture->advance('+970 seconds');
        $this->fixture->receive(LanGameFixture::alphaAgent(), self::finalSnapshot()->build(), 2, final: true);
    }

    public function testLePremierReleveOuvreLaPartieEtVerrouilleLesCartesDesParticipantsPresents(): void
    {
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);
        $this->fixture->picksDoubleKill(LanChallengeFixture::CHARLIE);

        $ignored = $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);

        $this->assertNull($ignored);
        $games = $this->fixture->games->all();
        $this->assertCount(1, $games);
        $this->assertSame(LanGameStatus::IN_PROGRESS, $games[0]->status);
        $this->assertTrue($this->fixture->run('puuid-a')->isLockedOn($games[0]->gameId));
        $this->assertTrue($this->fixture->run('puuid-c')->isLockedOn($games[0]->gameId), 'La carte de l\'adversaire, présent dans la partie, est verrouillée aussi.');
    }

    public function testLeReleveEstJournaliseSansSonContenuTantQuIlNEstPasFinal(): void
    {
        $this->playAGame();

        $records = $this->fixture->reportLog->records;
        $this->assertCount(2, $records);
        $this->assertSame(['digest-alpha', 'session-1', 1], [$records[0]['tokenDigest'], $records[0]['sessionId'], $records[0]['sequence']]);
        $this->assertNull($records[0]['finalPayload']);
        $this->assertSame('{"brut":2}', $records[1]['finalPayload']);
        $this->assertSame($records[0]['gameId'], $records[1]['gameId']);
        $this->assertEquals(new \DateTimeImmutable('2026-11-07 21:16:10'), $records[1]['receivedAt']);
    }

    public function testLeReleveFinalRegleLaManche(): void
    {
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);

        $this->playAGame();

        $game = $this->fixture->games->all()[0];
        $this->assertSame(LanGameStatus::SETTLED, $game->status);
        $this->assertEquals(new \DateTimeImmutable('2026-11-07 21:16:10'), $game->lastReportAt);
        $run = $this->fixture->run('puuid-a');
        $this->assertCount(1, $run->results);
        $this->assertSame($game->gameId, $run->results[0]->gameId);
        $this->assertSame(Verdict::SUCCESS, $run->results[0]->verdict);
        $this->assertNull($run->lockedGameId);
    }

    public function testLeReleveFinalJugeSurLesStatistiquesDeLaPartie(): void
    {
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
        $this->fixture->advance('+970 seconds');

        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->at(1000)->withScore('Alpha#EUW', 1, 3)->build(), 2, final: true);

        $this->assertSame(Verdict::FAILURE, $this->fixture->run('puuid-a')->results[0]->verdict);
    }

    public function testUnReleveIntermediaireNeRegleRien(): void
    {
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
        $this->fixture->advance('+15 seconds');

        $this->assertNull($this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->at(45)->build(), 2));

        $game = $this->fixture->games->all()[0];
        $this->assertSame(LanGameStatus::IN_PROGRESS, $game->status);
        $this->assertEquals(new \DateTimeImmutable('2026-11-07 21:00:15'), $game->lastReportAt);
        $this->assertSame([], $this->fixture->run('puuid-a')->results);
    }

    public function testUnePartieRejointeTropTardNEngageAucuneCarte(): void
    {
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);

        $ignored = $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->at(91)->build(), 1);
        $this->fixture->advance('+900 seconds');
        $ignoredFinal = $this->fixture->receive(LanGameFixture::alphaAgent(), self::finalSnapshot()->build(), 2, final: true);

        $this->assertSame(IgnoredReason::JOINED_TOO_LATE, $ignored);
        $this->assertSame(IgnoredReason::JOINED_TOO_LATE, $ignoredFinal);
        $this->assertSame(LanGameStatus::JOINED_TOO_LATE, $this->fixture->games->all()[0]->status);
        $run = $this->fixture->run('puuid-a');
        $this->assertNull($run->lockedGameId);
        $this->assertSame([], $run->results);
    }

    public function testLeReleveDUnSecondAgentRetombeSurLaMemePartie(): void
    {
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
        $this->fixture->advance('+20 seconds');

        // Charlie, dans l'équipe adverse, a lancé son agent un peu plus tard : 40 s de décalage d'horloge.
        $this->fixture->receive(LanGameFixture::charlieAgent(), GameSnapshotBuilder::aGame()->seenBy('Charlie#EUW')->at(10)->build(), 1);

        $this->assertCount(1, $this->fixture->games->all());
    }

    public function testUnePauseDePlusDUneMinuteNeCoupePasLaPartie(): void
    {
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
        // Trois minutes de pause : le temps de jeu s'est arrêté, l'heure de début estimée a glissé.
        $this->fixture->advance('+1150 seconds');

        $this->fixture->receive(LanGameFixture::alphaAgent(), self::finalSnapshot()->build(), 2, final: true);

        $this->assertCount(1, $this->fixture->games->all(), 'Même session : même partie, quelle que soit la dérive.');
        $this->assertSame(Verdict::SUCCESS, $this->fixture->run('puuid-a')->results[0]->verdict);
    }

    public function testUnSecondReleveFinalConcordantNeRejouePasLeReglement(): void
    {
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);
        $this->fixture->receive(LanGameFixture::charlieAgent(), GameSnapshotBuilder::aGame()->seenBy('Charlie#EUW')->build(), 1);
        $this->playAGame();

        $this->fixture->receive(LanGameFixture::charlieAgent(), self::finalSnapshot()->seenBy('Charlie#EUW')->build(), 2, final: true);

        $this->assertCount(1, $this->fixture->run('puuid-a')->results);
        $this->assertSame([], $this->fixture->logger->records('warning'));
    }

    public function testUnSecondReleveFinalContradictoireEstJournaliseEtEcarte(): void
    {
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);
        $this->fixture->receive(LanGameFixture::charlieAgent(), GameSnapshotBuilder::aGame()->seenBy('Charlie#EUW')->build(), 1);
        $this->playAGame();

        $this->fixture->receive(LanGameFixture::charlieAgent(), self::finalSnapshot(alphaKills: 3)->seenBy('Charlie#EUW')->build(), 2, final: true);

        $this->assertSame(Verdict::SUCCESS, $this->fixture->run('puuid-a')->results[0]->verdict);
        $warnings = $this->fixture->logger->records('warning');
        $this->assertCount(1, $warnings);
        $this->assertSame(['gameId' => $this->fixture->games->all()[0]->gameId], $warnings[0]['context']);
    }

    public function testUnReleveRejoueOuEnRetardEstIgnore(): void
    {
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 3);
        $this->fixture->advance('+15 seconds');

        $this->assertSame(IgnoredReason::STALE_SEQUENCE, $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->at(45)->build(), 3));
        $this->fixture->advance('+15 seconds');
        $this->assertSame(IgnoredReason::STALE_SEQUENCE, $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->at(60)->build(), 2));
        $this->assertCount(1, $this->fixture->reportLog->records);
    }

    public function testLaSequenceRepartDeUnAChaqueSession(): void
    {
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 5);
        $this->fixture->advance('+30 minutes');

        $this->assertNull($this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1, sessionId: 'session-2'));
        $this->assertCount(2, $this->fixture->games->all(), 'Nouvelle session, nouvelle partie.');
    }

    public function testUnAgentNEnvoiePasPlusDUnReleveToutesLesCinqSecondes(): void
    {
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
        $this->fixture->advance('+4 seconds');

        $this->expectException(ReportTooFrequentException::class);

        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->at(34)->build(), 2);
    }

    public function testCinqSecondesPlusTardLeReleveEstAccepte(): void
    {
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
        $this->fixture->advance('+5 seconds');

        $this->assertNull($this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->at(35)->build(), 2));
    }

    public function testLaCadenceEstCompteeParAgent(): void
    {
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);

        $this->assertNull($this->fixture->receive(LanGameFixture::charlieAgent(), GameSnapshotBuilder::aGame()->seenBy('Charlie#EUW')->build(), 1));
    }

    public function testLeReleveFinalNEstJamaisFreine(): void
    {
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
        $this->fixture->advance('+1 second');

        $this->assertNull($this->fixture->receive(LanGameFixture::alphaAgent(), self::finalSnapshot()->at(31)->build(), 2, final: true));
        $this->assertCount(1, $this->fixture->run('puuid-a')->results);
    }

    public function testUnAgentNEnvoieQueSaProprePartie(): void
    {
        $this->expectException(NotYourAccountException::class);

        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->seenBy('Bravo#EUW')->build(), 1);
    }

    public function testLeJoueurDuPosteEstReconnuSansTenirCompteDeLaCasse(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->replacing('Alpha#EUW', 'ALPHA#euw')->seenBy('ALPHA#euw')->build();

        $this->assertNull($this->fixture->receive(LanGameFixture::alphaAgent(), $snapshot, 1));
    }

    public function testAvantLeDebutDeLaLanLeReleveEstIgnore(): void
    {
        $this->fixture->at('2026-11-07 13:59:59');

        $this->assertSame(IgnoredReason::LAN_NOT_STARTED, $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1));
        $this->assertSame([], $this->fixture->games->all());
    }

    public function testUnAutreModeDeJeuEstIgnore(): void
    {
        $ignored = $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->inMode('CLASSIC')->build(), 1);

        $this->assertSame(IgnoredReason::GAME_MODE_NOT_ACCEPTED, $ignored);
        $this->assertSame([], $this->fixture->reportLog->records);
    }

    public function testUnePartieSansParticipantDeLaLanEstIgnoree(): void
    {
        // Alpha joue avec un compte lié mais qui ne participe pas à cette LAN.
        $this->fixture->lan->accounts->add(new TrackedRiotAccount('Smurf#EUW', 'puuid-smurf', LanChallengeFixture::ALPHA));
        $snapshot = GameSnapshotBuilder::aGame()
            ->replacing('Alpha#EUW', 'Smurf#EUW')->replacing('Bravo#EUW', 'Inconnu8#EUW')->replacing('Charlie#EUW', 'Inconnu9#EUW')
            ->seenBy('Smurf#EUW')->build();

        $this->assertSame(IgnoredReason::NO_PARTICIPANT_IN_GAME, $this->fixture->receive(LanGameFixture::alphaAgent(), $snapshot, 1));
    }

    public function testApresLaFinDeLaLanSeulLeReleveFinalPasse(): void
    {
        $this->fixture->at('2026-11-08 04:00:01');

        $this->expectException(AgentTokenExpiredException::class);

        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
    }

    public function testUnePartieCommenceeAvantLaFinDeLaLanEstRegleeApres(): void
    {
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);
        $this->fixture->at('2026-11-08 03:59:00');
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
        $this->fixture->at('2026-11-08 04:15:00');

        $this->assertNull($this->fixture->receive(LanGameFixture::alphaAgent(), self::finalSnapshot()->build(), 2, final: true));
        $this->assertSame(Verdict::SUCCESS, $this->fixture->run('puuid-a')->results[0]->verdict);
    }

    public function testUnePartieCommenceeApresLaFinDeLaLanNeComptePas(): void
    {
        $this->fixture->at('2026-11-08 04:30:00');

        $ignored = $this->fixture->receive(LanGameFixture::alphaAgent(), self::finalSnapshot()->at(60)->build(), 1, final: true);

        $this->assertSame(IgnoredReason::LAN_FINISHED, $ignored);
        $this->assertSame([], $this->fixture->games->all());
    }

    public function testUnJetonDontLaLanNExistePlusEstRefuse(): void
    {
        $orphan = new AgentToken('digest-orphan', new DiscordIdentity(LanChallengeFixture::ALPHA, 'alpha'), 99, new \DateTimeImmutable('2026-11-08 04:00'));

        $this->expectException(LanNotFoundException::class);

        $this->fixture->receive($orphan, GameSnapshotBuilder::aGame()->build(), 1);
    }
}
