<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\RankedRace\RaceFreshness;
use PHPUnit\Framework\TestCase;

class RaceFreshnessTest extends TestCase
{
    public function testAgeEtProchainRafraichissement(): void
    {
        $freshness = RaceFreshness::at(
            new \DateTimeImmutable('2026-08-15 16:21:48'),
            new \DateTimeImmutable('2026-08-15 15:08:28'),
        );

        $this->assertSame(4400, $freshness->snapshotAgeSeconds);
        $this->assertSame('2026-08-15 15:38:28', $freshness->nextRefreshAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 16:21:48', $freshness->generatedAt->format('Y-m-d H:i:s'));
    }

    public function testAucunReleveConnu(): void
    {
        // File jamais collectée, ou base fraîchement installée : le front doit
        // pouvoir distinguer « pas de donnée » de « donnée à l'instant ».
        $freshness = RaceFreshness::at(new \DateTimeImmutable('2026-08-15 16:21:48'), null);

        $this->assertNull($freshness->lastSnapshotAt);
        $this->assertNull($freshness->nextRefreshAt);
        $this->assertNull($freshness->snapshotAgeSeconds);
    }

    public function testUnReleveDateDansLeFuturNeProduitPasDAgeNegatif(): void
    {
        // Les horloges du serveur web et du cron peuvent diverger : afficher
        // « mis à jour il y a -3 min » est le bug classique de ce champ.
        $freshness = RaceFreshness::at(
            new \DateTimeImmutable('2026-08-15 16:00:00'),
            new \DateTimeImmutable('2026-08-15 16:03:00'),
        );

        $this->assertSame(0, $freshness->snapshotAgeSeconds);
    }

    public function testLeFuseauEstRespecteDansLeCalculDAge(): void
    {
        // Le relevé est en heure civile Paris, l'instant courant aussi :
        // l'écart doit être de 10 minutes, pas de 2 heures et 10 minutes.
        $paris = new \DateTimeZone('Europe/Paris');
        $freshness = RaceFreshness::at(
            new \DateTimeImmutable('2026-08-15 15:20:00', $paris),
            new \DateTimeImmutable('2026-08-15 15:10:00', $paris),
        );

        $this->assertSame(600, $freshness->snapshotAgeSeconds);
    }
}
