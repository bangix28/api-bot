<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\RankedRace\RacePlayer;
use App\Domain\RankedRace\RaceSegment;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use PHPUnit\Framework\TestCase;

class RaceSegmentTest extends TestCase
{
    public function testAucunSegmentSansPartieJouee(): void
    {
        // Decay : les LP baissent, le compteur de parties ne bouge pas.
        $from = $this->snapshot('2026-08-03 10:00', RankedTier::DIAMOND, 50, wins: 40, losses: 40);
        $to = $this->snapshot('2026-08-03 10:30', RankedTier::DIAMOND, 0, wins: 40, losses: 40);

        $this->assertNull(RaceSegment::between($from, $to));
    }

    public function testAucunSegmentQuandLeCompteurDePartiesDiminue(): void
    {
        // Seul un reset de saison peut faire reculer wins+losses.
        $from = $this->snapshot('2026-08-03 10:00', RankedTier::GOLD, 10, wins: 100, losses: 100);
        $to = $this->snapshot('2026-08-04 10:00', RankedTier::GOLD, 30, wins: 2, losses: 3);

        $this->assertNull(RaceSegment::between($from, $to));
    }

    public function testPlusieursPartiesDansUnMemeIntervalleDeTrenteMinutes(): void
    {
        // Deux parties enchaînées entre deux relevés : un seul segment les porte.
        $from = $this->snapshot('2026-08-03 10:00', RankedTier::GOLD, 20, wins: 10, losses: 10);
        $to = $this->snapshot('2026-08-03 10:30', RankedTier::GOLD, 60, wins: 12, losses: 10);

        $segment = RaceSegment::between($from, $to);

        $this->assertNotNull($segment);
        $this->assertSame(2, $segment->games());
        $this->assertSame(2, $segment->wins());
        $this->assertSame(40, $segment->scoreDelta());
    }

    public function testLeDeltaEstDecoupeALaFrontiereDeTier(): void
    {
        // Gold I 80 (1580) -> Platinum IV 10 (1610) : 20 LP gagnés en Gold à 1.25
        // (25) puis 10 LP en Platinum à 1.4 (14), soit 39. Chaque LP est payé au
        // tarif du palier où il a été gagné, pas à celui du palier de départ.
        $from = $this->snapshot('2026-08-03 10:00', RankedTier::GOLD, 80, wins: 10, losses: 10, division: RankedRank::I);
        $to = $this->snapshot('2026-08-03 10:30', RankedTier::PLATINUM, 10, wins: 11, losses: 10, division: RankedRank::IV);

        $segment = RaceSegment::between($from, $to);

        $this->assertNotNull($segment);
        $this->assertSame(30, $segment->scoreDelta());
        $this->assertSame(39.0, $segment->weightedDelta());
    }

    public function testUneDefaiteProduitUnSegmentNegatif(): void
    {
        // Perdre en jouant compte, contrairement au decay : c'est le sens même
        // de la règle « pas de partie, pas de delta ».
        $from = $this->snapshot('2026-08-03 10:00', RankedTier::SILVER, 40, wins: 10, losses: 10);
        $to = $this->snapshot('2026-08-03 10:30', RankedTier::SILVER, 20, wins: 10, losses: 11);

        $segment = RaceSegment::between($from, $to);

        $this->assertNotNull($segment);
        $this->assertSame(1, $segment->games());
        $this->assertSame(0, $segment->wins());
        $this->assertSame(-20, $segment->scoreDelta());
        $this->assertSame(-22.0, $segment->weightedDelta()); // x1.1 Silver
    }

    private function snapshot(
        string $capturedAt,
        RankedTier $tier,
        int $leaguePoints,
        int $wins,
        int $losses,
        RankedRank $division = RankedRank::II,
    ): RaceSnapshot {
        return new RaceSnapshot(
            new RacePlayer('Toto#EUW', 'Toto', '685'),
            new \DateTimeImmutable($capturedAt),
            new RankedQueueEntity($division, $tier, $leaguePoints, $wins, $losses),
        );
    }
}
