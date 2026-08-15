<?php

namespace App\Tests\Domain\EloSnapshot;

use App\Domain\EloSnapshot\EloChangeDetector;
use App\Domain\EloSnapshot\EloSnapshot;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use PHPUnit\Framework\TestCase;

class EloChangeDetectorTest extends TestCase
{
    public function testLePremierReleveEstToujoursEcrit(): void
    {
        $this->assertTrue(EloChangeDetector::shouldRecord(null, $this->snapshot('2026-08-03 10:00', 50)));
    }

    public function testRangIdentiqueDansLaMemeJourneeNEcritRien(): void
    {
        // Le cas nominal : 48 relevés par jour, la quasi-totalité inchangés.
        $last = $this->snapshot('2026-08-03 10:00', 50);
        $candidate = $this->snapshot('2026-08-03 10:30', 50);

        $this->assertFalse(EloChangeDetector::shouldRecord($last, $candidate));
    }

    public function testUnChangementDeLpEstEcrit(): void
    {
        $last = $this->snapshot('2026-08-03 10:00', 50);
        $candidate = $this->snapshot('2026-08-03 10:30', 68);

        $this->assertTrue(EloChangeDetector::shouldRecord($last, $candidate));
    }

    public function testUnChangementDeVictoiresAEgaliteDeLpEstEcrit(): void
    {
        // Deux parties, une gagnée une perdue : les LP peuvent revenir au même
        // total alors que des parties ont bel et bien été jouées. Sans ce test,
        // la course perdrait des parties.
        $last = $this->snapshot('2026-08-03 10:00', 50, wins: 10, losses: 10);
        $candidate = $this->snapshot('2026-08-03 10:30', 50, wins: 11, losses: 11);

        $this->assertTrue(EloChangeDetector::shouldRecord($last, $candidate));
    }

    public function testUnChangementDeTierEstEcrit(): void
    {
        $last = $this->snapshot('2026-08-03 10:00', 99, tier: RankedTier::GOLD);
        $candidate = $this->snapshot('2026-08-03 10:30', 10, tier: RankedTier::PLATINUM);

        $this->assertTrue(EloChangeDetector::shouldRecord($last, $candidate));
    }

    public function testUnBattementParJourMemeSansChangement(): void
    {
        // Garantit à chaque fenêtre de course un point de départ proche de sa
        // borne, sans dépendre de la reprise du dernier point antérieur.
        $last = $this->snapshot('2026-08-03 23:30', 50);
        $candidate = $this->snapshot('2026-08-04 00:00', 50);

        $this->assertTrue(EloChangeDetector::shouldRecord($last, $candidate));
    }

    public function testUnSeulBattementParJour(): void
    {
        // Le battement de 00:00 écrit ; celui de 00:30 ne doit plus rien écrire.
        $last = $this->snapshot('2026-08-04 00:00', 50);
        $candidate = $this->snapshot('2026-08-04 00:30', 50);

        $this->assertFalse(EloChangeDetector::shouldRecord($last, $candidate));
    }

    public function testUnReleveAnterieurOuSimultaneEstRefuse(): void
    {
        // Garde de monotonie : run concurrent, horloge revenue en arrière, ou
        // heure d'hiver où 02:30 existe deux fois. Violer l'unique fermerait
        // l'EntityManager et condamnerait la suite du run.
        $last = $this->snapshot('2026-08-03 10:30', 50);

        $this->assertFalse(EloChangeDetector::shouldRecord($last, $this->snapshot('2026-08-03 10:00', 80)));
        $this->assertFalse(EloChangeDetector::shouldRecord($last, $this->snapshot('2026-08-03 10:30', 80)));
    }

    private function snapshot(
        string $capturedAt,
        int $leaguePoints,
        RankedTier $tier = RankedTier::GOLD,
        int $wins = 10,
        int $losses = 10,
    ): EloSnapshot {
        return new EloSnapshot(
            'puuid-1',
            new \DateTimeImmutable($capturedAt),
            RankedQueueType::SOLO,
            new RankedQueueEntity(RankedRank::II, $tier, $leaguePoints, $wins, $losses),
        );
    }
}
