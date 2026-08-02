<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\RankedRace\PlayerRaceSeries;
use App\Domain\RankedRace\RacePlayer;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use PHPUnit\Framework\TestCase;

class PlayerRaceSeriesTest extends TestCase
{
    public function testProgressionBruteEtPondereeAvecFranchissementDeTier(): void
    {
        // Gold I 80 LP (4480) -> Platinum IV 10 LP (5110) : delta 630, payé au tarif Gold (x1.25).
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03', RankedTier::GOLD, RankedRank::I, 80, wins: 10, losses: 10),
            $this->snapshot('2026-08-04', RankedTier::PLATINUM, RankedRank::IV, 10, wins: 18, losses: 14),
        ]);

        $this->assertSame(630, $series->rawProgression());
        $this->assertSame(787.5, $series->weightedProgression());
    }

    public function testLesTrousDansLaSerieSontCalculesEntreSnapshotsConnus(): void
    {
        // Pas de snapshot le 4 (cron en panne, compte non classé ce jour-là...) :
        // un seul delta entre le 3 et le 5, pondéré par le tier de départ.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03', RankedTier::SILVER, RankedRank::II, 50),
            $this->snapshot('2026-08-05', RankedTier::SILVER, RankedRank::I, 20),
        ]);

        // 3350 -> 3420 : delta 70 x 1.1 (Silver)
        $this->assertSame(70, $series->rawProgression());
        $this->assertSame(77.0, $series->weightedProgression());
    }

    public function testSnapshotUniqueEntreeEnCoursDePeriode(): void
    {
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-05', RankedTier::EMERALD, RankedRank::III, 40, wins: 100, losses: 90),
        ]);

        $this->assertSame(0, $series->rawProgression());
        $this->assertSame(0.0, $series->weightedProgression());
        $this->assertSame(0, $series->gamesPlayed());
        $this->assertNull($series->winrate());
    }

    public function testLeDecayCompteEnNegatif(): void
    {
        // Perte de LP sans partie (decay, dodge) : la course récompense l'activité.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03', RankedTier::DIAMOND, RankedRank::IV, 50, wins: 40, losses: 40),
            $this->snapshot('2026-08-04', RankedTier::DIAMOND, RankedRank::IV, 0, wins: 40, losses: 40),
        ]);

        $this->assertSame(-50, $series->rawProgression());
        $this->assertSame(-90.0, $series->weightedProgression()); // x1.8 Diamond
        $this->assertSame(0, $series->gamesPlayed());
    }

    public function testLesDeltasDeGamesSontClampesAZeroAuResetDeSaison(): void
    {
        // Reset Riot : wins/losses repartent de zéro, le delta brut serait négatif.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03', RankedTier::GOLD, RankedRank::II, 10, wins: 100, losses: 100),
            $this->snapshot('2026-08-04', RankedTier::GOLD, RankedRank::II, 30, wins: 2, losses: 3),
        ]);

        $this->assertSame(0, $series->gamesPlayed());
        $this->assertSame(0, $series->winsDelta());
        $this->assertNull($series->winrate());
    }

    public function testWinrateEtQualification(): void
    {
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03', RankedTier::GOLD, RankedRank::II, 10, wins: 20, losses: 20),
            $this->snapshot('2026-08-09', RankedTier::GOLD, RankedRank::I, 40, wins: 28, losses: 24),
        ]);

        $this->assertSame(12, $series->gamesPlayed());
        $this->assertSame(8, $series->winsDelta());
        $this->assertSame(66.7, $series->winrate());
        $this->assertTrue($series->isQualified(5));   // seuil hebdo
        $this->assertFalse($series->isQualified(15)); // seuil mensuel
    }

    public function testLesSnapshotsSontReordonnesParDate(): void
    {
        // Défensif : l'ordre d'arrivée ne doit pas changer le résultat.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-04', RankedTier::SILVER, RankedRank::I, 20),
            $this->snapshot('2026-08-03', RankedTier::SILVER, RankedRank::II, 50),
        ]);

        $this->assertSame(70, $series->rawProgression());
    }

    public function testSerieVideRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PlayerRaceSeries($this->player(), []);
    }

    private function player(): RacePlayer
    {
        return new RacePlayer('Toto#EUW', 'Toto', '685');
    }

    private function snapshot(
        string $day,
        RankedTier $tier,
        RankedRank $division,
        int $leaguePoints,
        int $wins = 0,
        int $losses = 0,
    ): RaceSnapshot {
        return new RaceSnapshot(
            $this->player(),
            new \DateTimeImmutable($day),
            new RankedQueueEntity($division, $tier, $leaguePoints, $wins, $losses),
        );
    }
}
