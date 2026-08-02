<?php

namespace App\Tests\Application\RankedRace;

use App\Application\RankedRace\ComputeStandings\ComputeRankedRaceStandingsCommand;
use App\Application\RankedRace\ComputeStandings\ComputeRankedRaceStandingsHandler;
use App\Domain\RankedRace\InvalidRankedRaceParameterException;
use App\Domain\RankedRace\RacePlayer;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Tests\Domain\RankedRace\InMemoryRaceSnapshotRepository;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;

class ComputeRankedRaceStandingsHandlerTest extends TestCase
{
    // Mercredi -> fenêtre hebdo du lundi 3 au dimanche 9 août.
    private const string TODAY = '2026-08-05';

    public function testStandingsCompletsSurUneSemaine(): void
    {
        // Arrange : Toto grimpe Gold->Platinum (12 parties), Tata stagne (3 parties).
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $tata = new RacePlayer('Tata#EUW', 'Tata', '12');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-03', RankedTier::GOLD, RankedRank::I, 80, 10, 10),
            $this->snapshot($toto, '2026-08-05', RankedTier::PLATINUM, RankedRank::IV, 10, 18, 14),
            $this->snapshot($tata, '2026-08-03', RankedTier::SILVER, RankedRank::III, 20, 30, 30),
            $this->snapshot($tata, '2026-08-05', RankedTier::SILVER, RankedRank::III, 35, 32, 31),
            // Hors fenêtre : ne doit pas compter.
            $this->snapshot($toto, '2026-07-30', RankedTier::GOLD, RankedRank::II, 0, 0, 0),
        ]);

        // Act
        $view = $this->handler($repository)->handle(new ComputeRankedRaceStandingsCommand('solo', 'week'));

        // Assert : fenêtre et métadonnées
        $this->assertSame('solo', $view->queue);
        $this->assertSame('week', $view->period);
        $this->assertSame('2026-08-03', $view->windowStart);
        $this->assertSame('2026-08-09', $view->windowEnd);
        $this->assertFalse($view->progressionSuspended);

        // Progression : Toto devant (630 bruts x1.25 = 787.5), Tata ensuite (15 x1.1 = 16.5)
        $this->assertCount(2, $view->progression);
        $this->assertSame('Toto#EUW', $view->progression[0]->riotId);
        $this->assertSame(630, $view->progression[0]->rawDelta);
        $this->assertSame(787.5, $view->progression[0]->weightedDelta);
        $this->assertSame(1, $view->progression[0]->rankRaw);
        $this->assertSame(1, $view->progression[0]->rankWeighted);
        $this->assertSame('GOLD', $view->progression[0]->start->tier);
        $this->assertSame('PLATINUM', $view->progression[0]->end->tier);

        // Winrate hebdo (seuil 5) : Toto qualifié (12 parties), Tata grisé (3/5).
        $this->assertSame(5, $view->winrate->gamesRequired);
        $this->assertCount(1, $view->winrate->qualified);
        $this->assertSame('Toto#EUW', $view->winrate->qualified[0]->riotId);
        $this->assertSame(8, $view->winrate->qualified[0]->wins);
        $this->assertSame(4, $view->winrate->qualified[0]->losses);
        $this->assertSame(66.7, $view->winrate->qualified[0]->winrate);
        $this->assertCount(1, $view->winrate->notQualified);
        $this->assertSame(3, $view->winrate->notQualified[0]->gamesPlayed);
    }

    public function testEntreeEnCoursDePeriode(): void
    {
        // Un seul snapshot (inscrit hier) : progression neutre, présent au classement.
        $nouveau = new RacePlayer('Nouveau#EUW', 'Nouveau', '1');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($nouveau, '2026-08-04', RankedTier::EMERALD, RankedRank::II, 55, 40, 38),
        ]);

        $view = $this->handler($repository)->handle(new ComputeRankedRaceStandingsCommand());

        $this->assertCount(1, $view->progression);
        $this->assertSame(0, $view->progression[0]->rawDelta);
        $this->assertSame(0, $view->progression[0]->gamesPlayed);
        $this->assertCount(1, $view->winrate->notQualified); // 0/5 parties
    }

    public function testSuspensionPendantLesPlacements(): void
    {
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-03', RankedTier::GOLD, RankedRank::I, 80, 10, 10),
            $this->snapshot($toto, '2026-08-05', RankedTier::PLATINUM, RankedRank::IV, 10, 18, 14),
        ]);

        $view = $this->handler($repository, progressionSuspended: true)
            ->handle(new ComputeRankedRaceStandingsCommand());

        // La Progression est vide et signalée suspendue ; le Winrate continue.
        $this->assertTrue($view->progressionSuspended);
        $this->assertSame([], $view->progression);
        $this->assertCount(1, $view->winrate->qualified);
    }

    public function testFileInvalideRejetee(): void
    {
        $this->expectException(InvalidRankedRaceParameterException::class);

        $this->handler(new InMemoryRaceSnapshotRepository())
            ->handle(new ComputeRankedRaceStandingsCommand(queue: 'aram'));
    }

    public function testPeriodeInvalideRejetee(): void
    {
        $this->expectException(InvalidRankedRaceParameterException::class);

        $this->handler(new InMemoryRaceSnapshotRepository())
            ->handle(new ComputeRankedRaceStandingsCommand(period: 'year'));
    }

    private function handler(
        InMemoryRaceSnapshotRepository $repository,
        bool $progressionSuspended = false,
    ): ComputeRankedRaceStandingsHandler {
        return new ComputeRankedRaceStandingsHandler(
            $repository,
            new FixedClock(new \DateTimeImmutable(self::TODAY)),
            progressionSuspended: $progressionSuspended,
        );
    }

    private function snapshot(
        RacePlayer $player,
        string $day,
        RankedTier $tier,
        RankedRank $division,
        int $leaguePoints,
        int $wins,
        int $losses,
    ): RaceSnapshot {
        return new RaceSnapshot(
            $player,
            new \DateTimeImmutable($day),
            new RankedQueueEntity($division, $tier, $leaguePoints, $wins, $losses),
        );
    }
}
