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

        // Progression : Toto devant (30 bruts, tarif plat 1.0 de Gold à Platine),
        // Tata ensuite (15 x1.0 en Silver). Sous Emerald, les deux classements
        // affichent le même chiffre.
        $this->assertCount(2, $view->progression);
        $this->assertSame('Toto#EUW', $view->progression[0]->riotId);
        $this->assertSame(30, $view->progression[0]->rawDelta);
        $this->assertSame(30.0, $view->progression[0]->weightedDelta);
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

    public function testLeReportSertDeRangDeDepartAUnJoueurInactifEnDebutDeSemaine(): void
    {
        // Toto joue le dimanche soir jusqu'à 23h30, puis une partie le mercredi.
        // Le relevé de 23h30 précède la fenêtre : sans le report, le rang de
        // départ serait celui d'APRÈS sa partie du mercredi, qui serait perdue.
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-02 23:30', RankedTier::GOLD, RankedRank::I, 30, 10, 10),
            $this->snapshot($toto, '2026-08-05 21:00', RankedTier::GOLD, RankedRank::I, 48, 11, 10),
        ]);

        $view = $this->handler($repository)->handle(new ComputeRankedRaceStandingsCommand());

        $this->assertCount(1, $view->progression);
        // La partie du mercredi est bien comptée, depuis le rang du dimanche.
        $this->assertSame(18, $view->progression[0]->rawDelta);
        $this->assertSame(1, $view->progression[0]->gamesPlayed);
        $this->assertSame(30, $view->progression[0]->start->leaguePoints);
    }

    public function testUnReportTropAncienNEstPasUtilise(): void
    {
        // Relevé de la veille au matin : le segment le reliant au premier relevé
        // de la semaine créditerait à celle-ci les 20 parties du dimanche. On
        // préfère un joueur à zéro partie qu'un score gonflé par la période
        // précédente. C'est ce que produisent les données quotidiennes reprises.
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-02 03:00', RankedTier::SILVER, RankedRank::IV, 0, 40, 40),
            $this->snapshot($toto, '2026-08-05 21:00', RankedTier::GOLD, RankedRank::I, 48, 60, 40),
        ]);

        $view = $this->handler($repository)->handle(new ComputeRankedRaceStandingsCommand());

        $this->assertCount(1, $view->progression);
        $this->assertSame(0, $view->progression[0]->rawDelta);
        $this->assertSame(0, $view->progression[0]->gamesPlayed);
    }

    public function testLesChampsDeFraicheurEtDeStatutSontRenseignes(): void
    {
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-03 03:00', RankedTier::GOLD, RankedRank::I, 80, 10, 10),
            $this->snapshot($toto, '2026-08-05 21:00', RankedTier::PLATINUM, RankedRank::IV, 10, 18, 14),
        ]);

        // Mercredi 23h00, dernier relevé à 21h00 : deux heures de retard.
        $handler = new ComputeRankedRaceStandingsHandler(
            $repository,
            new FixedClock(new \DateTimeImmutable('2026-08-05 23:00')),
        );
        $view = $handler->handle(new ComputeRankedRaceStandingsCommand());

        $this->assertSame('running', $view->raceStatus);
        $this->assertSame('2026-08-03T00:00:00+00:00', $view->windowIso['start']);
        // Fin EXCLUE : lundi minuit, pas dimanche 23h59.
        $this->assertSame('2026-08-10T00:00:00+00:00', $view->windowIso['endExclusive']);
        $this->assertSame('2026-08-05T21:00:00+00:00', $view->lastSnapshotAt);
        $this->assertSame('2026-08-05T21:30:00+00:00', $view->nextRefreshAt);
        $this->assertSame(7200, $view->snapshotAgeSeconds);

        // Départ et dernière activité du joueur, pour le badge « en série ».
        $this->assertSame('2026-08-05T21:00:00+00:00', $view->progression[0]->startedAt);
        $this->assertSame('2026-08-05T21:00:00+00:00', $view->progression[0]->lastActivityAt);
        $this->assertSame(0, $view->progression[0]->offRaceDelta);
    }

    public function testStatutQuandPersonneNAJoueDeLaSemaine(): void
    {
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-03 00:00', RankedTier::GOLD, RankedRank::I, 80, 10, 10),
            $this->snapshot($toto, '2026-08-04 00:00', RankedTier::GOLD, RankedRank::I, 80, 10, 10),
        ]);

        $view = $this->handler($repository)->handle(new ComputeRankedRaceStandingsCommand());

        $this->assertSame('awaiting_first_game', $view->raceStatus);
        $this->assertNull($view->progression[0]->startedAt);
    }

    public function testLeStatutEstIndependantDeLaSuspension(): void
    {
        // Une course peut être en cours avec la Progression masquée pendant
        // les placements : les deux notions ne doivent pas être fusionnées.
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-03 03:00', RankedTier::GOLD, RankedRank::I, 80, 10, 10),
            $this->snapshot($toto, '2026-08-05 03:00', RankedTier::PLATINUM, RankedRank::IV, 10, 18, 14),
        ]);

        $view = $this->handler($repository, progressionSuspended: true)
            ->handle(new ComputeRankedRaceStandingsCommand());

        $this->assertSame('running', $view->raceStatus);
        $this->assertTrue($view->progressionSuspended);
        $this->assertSame([], $view->progression);
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
