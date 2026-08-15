<?php

namespace App\Tests\Application\RankedRace;

use App\Application\RankedRace\StandingsViewAssembler;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\PlayerRaceSeries;
use App\Domain\RankedRace\RacePlayer;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RankedRace\RaceWindow;
use App\Domain\RankedRace\RankedGameStart;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Tests\Domain\RankedRace\FakeFirstRankedGameFinder;
use PHPUnit\Framework\TestCase;

class StandingsViewAssemblerTest extends TestCase
{
    public function testLeDepartEstAffineParLHeureReelleDeLaPartie(): void
    {
        // Le relevé constate la partie à 21h00 ; le match dit qu'elle a été
        // lancée à 20h27. C'est cette seconde valeur qu'on veut afficher.
        $finder = new FakeFirstRankedGameFinder([
            'Toto#EUW' => new RankedGameStart('EUW1_123', new \DateTimeImmutable('2026-08-05 20:27:11')),
        ]);
        $assembler = new StandingsViewAssembler(firstGames: $finder);

        $entries = $assembler->progression([$this->serieAvecUnePartie()], RankedQueueType::SOLO, $this->semaine());

        $this->assertSame('2026-08-05T20:27:11+00:00', $entries[0]->startedAt);
    }

    public function testSansMatchCollecteOnRetombeSurLInstantDObservation(): void
    {
        // Compte récent, match pas encore collecté : l'affinage ne doit rien
        // dégrader, on garde la date à la cadence du cron près.
        $assembler = new StandingsViewAssembler(firstGames: new FakeFirstRankedGameFinder());

        $entries = $assembler->progression([$this->serieAvecUnePartie()], RankedQueueType::SOLO, $this->semaine());

        $this->assertSame('2026-08-05T21:00:00+00:00', $entries[0]->startedAt);
    }

    public function testUnJoueurSansPartieNEstPasInterroge(): void
    {
        // Pas de segment, donc pas de départ à affiner : inutile d'aller
        // chercher un match qui n'existe pas.
        $finder = new FakeFirstRankedGameFinder();
        $assembler = new StandingsViewAssembler(firstGames: $finder);

        $entries = $assembler->progression([$this->serieSansPartie()], RankedQueueType::SOLO, $this->semaine());

        $this->assertNull($entries[0]->startedAt);
        $this->assertSame([], $finder->queriedQueues);
    }

    public function testLaFileDeLaCourseEstCelleTransmiseAuFinder(): void
    {
        // Une game flex ne doit pas dater le départ de la course solo.
        $finder = new FakeFirstRankedGameFinder();
        $assembler = new StandingsViewAssembler(firstGames: $finder);

        $assembler->progression([$this->serieAvecUnePartie()], RankedQueueType::FLEX, $this->semaine());

        $this->assertSame(['RANKED_FLEX_SR'], $finder->queriedQueues);
    }

    private function semaine(): RaceWindow
    {
        return RaceWindow::fromDays(new \DateTimeImmutable('2026-08-03'), new \DateTimeImmutable('2026-08-09'));
    }

    private function serieAvecUnePartie(): PlayerRaceSeries
    {
        $player = new RacePlayer('Toto#EUW', 'Toto', '685');

        return new PlayerRaceSeries($player, [
            $this->snapshot($player, '2026-08-05 20:30', 30, wins: 10, losses: 10),
            $this->snapshot($player, '2026-08-05 21:00', 48, wins: 11, losses: 10),
        ]);
    }

    private function serieSansPartie(): PlayerRaceSeries
    {
        $player = new RacePlayer('Toto#EUW', 'Toto', '685');

        return new PlayerRaceSeries($player, [
            $this->snapshot($player, '2026-08-05 20:30', 30, wins: 10, losses: 10),
        ]);
    }

    private function snapshot(RacePlayer $player, string $at, int $lp, int $wins, int $losses): RaceSnapshot
    {
        return new RaceSnapshot(
            $player,
            new \DateTimeImmutable($at),
            new RankedQueueEntity(RankedRank::IV, RankedTier::GOLD, $lp, $wins, $losses),
        );
    }
}
