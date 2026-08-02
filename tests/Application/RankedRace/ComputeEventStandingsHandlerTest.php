<?php

namespace App\Tests\Application\RankedRace;

use App\Application\RankedRace\ComputeEventStandings\ComputeEventStandingsCommand;
use App\Application\RankedRace\ComputeEventStandings\ComputeEventStandingsHandler;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\RaceEvent;
use App\Domain\RankedRace\RaceEventNotFoundException;
use App\Domain\RankedRace\RacePlayer;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RankedRace\RaceWindow;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Tests\Domain\RankedRace\InMemoryRaceEventRepository;
use App\Tests\Domain\RankedRace\InMemoryRaceSnapshotRepository;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;

class ComputeEventStandingsHandlerTest extends TestCase
{
    public function testStandingsDUnEvenementActif(): void
    {
        // Arrange : sprint du 10 au 24, seuil 3 parties ; aujourd'hui = pendant l'événement.
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $event = $this->event(id: 1, start: '2026-08-10', end: '2026-08-24', minGames: 3);
        $handler = $this->handler(
            $event,
            new InMemoryRaceSnapshotRepository([
                $this->snapshot($toto, '2026-08-10', RankedTier::GOLD, RankedRank::I, 80, 10, 10),
                $this->snapshot($toto, '2026-08-12', RankedTier::PLATINUM, RankedRank::IV, 10, 12, 11),
                // Hors fenêtre : ignoré.
                $this->snapshot($toto, '2026-08-01', RankedTier::GOLD, RankedRank::III, 0, 0, 0),
            ]),
            today: '2026-08-12',
        );

        // Act
        $view = $handler->handle(new ComputeEventStandingsCommand(1));

        // Assert : méta de l'événement
        $this->assertSame('Sprint de test', $view->name);
        $this->assertSame('solo', $view->queue);
        $this->assertSame('2026-08-10', $view->windowStart);
        $this->assertSame('2026-08-24', $view->windowEnd);
        $this->assertSame('active', $view->status);
        $this->assertSame(3, $view->minGamesToQualify);

        // Standings : fenêtre respectée, seuil custom appliqué (3 parties -> qualifié,
        // alors que la règle hebdo à 5 l'aurait grisé).
        $this->assertCount(1, $view->progression);
        $this->assertSame(630, $view->progression[0]->rawDelta);
        $this->assertSame(3, $view->winrate->gamesRequired);
        $this->assertCount(1, $view->winrate->qualified);
    }

    public function testEvenementTermineGardeSonPalmares(): void
    {
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $event = $this->event(id: 1, start: '2026-07-01', end: '2026-07-15', minGames: 5);
        $handler = $this->handler(
            $event,
            new InMemoryRaceSnapshotRepository([
                $this->snapshot($toto, '2026-07-01', RankedTier::SILVER, RankedRank::II, 0, 10, 10),
                $this->snapshot($toto, '2026-07-15', RankedTier::SILVER, RankedRank::I, 50, 18, 14),
            ]),
            today: '2026-08-12', // un mois après la fin
        );

        $view = $handler->handle(new ComputeEventStandingsCommand(1));

        $this->assertSame('finished', $view->status);
        $this->assertCount(1, $view->progression); // le palmarès reste calculable
        $this->assertSame(150, $view->progression[0]->rawDelta);
    }

    public function testEvenementInconnuRejete(): void
    {
        $handler = $this->handler(
            $this->event(id: 1, start: '2026-08-10', end: '2026-08-24', minGames: 5),
            new InMemoryRaceSnapshotRepository(),
            today: '2026-08-12',
        );

        $this->expectException(RaceEventNotFoundException::class);

        $handler->handle(new ComputeEventStandingsCommand(999));
    }

    public function testSuspensionMasqueLaProgressionMaisPasLeWinrate(): void
    {
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $event = $this->event(id: 1, start: '2026-08-10', end: '2026-08-24', minGames: 3);
        $handler = $this->handler(
            $event,
            new InMemoryRaceSnapshotRepository([
                $this->snapshot($toto, '2026-08-10', RankedTier::GOLD, RankedRank::I, 80, 10, 10),
                $this->snapshot($toto, '2026-08-12', RankedTier::PLATINUM, RankedRank::IV, 10, 12, 11),
            ]),
            today: '2026-08-12',
            progressionSuspended: true,
        );

        $view = $handler->handle(new ComputeEventStandingsCommand(1));

        $this->assertTrue($view->progressionSuspended);
        $this->assertSame([], $view->progression);
        $this->assertCount(1, $view->winrate->qualified);
    }

    private function event(int $id, string $start, string $end, int $minGames): RaceEvent
    {
        return new RaceEvent(
            $id,
            'Sprint de test',
            RankedQueueType::SOLO,
            new RaceWindow(new \DateTimeImmutable($start), new \DateTimeImmutable($end)),
            $minGames,
        );
    }

    private function handler(
        RaceEvent $event,
        InMemoryRaceSnapshotRepository $snapshots,
        string $today,
        bool $progressionSuspended = false,
    ): ComputeEventStandingsHandler {
        return new ComputeEventStandingsHandler(
            new InMemoryRaceEventRepository([$event]),
            $snapshots,
            new FixedClock(new \DateTimeImmutable($today)),
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
