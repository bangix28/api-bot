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
        // Gold I 80 LP (1580) -> Platinum IV 10 LP (1610) : 30 LP réels, découpés
        // à la frontière — 20 en Gold à 1.25, 10 en Platinum à 1.4, soit 39.
        // La promotion ne rapporte rien en elle-même.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03 03:00', RankedTier::GOLD, RankedRank::I, 80, wins: 10, losses: 10),
            $this->snapshot('2026-08-04 03:00', RankedTier::PLATINUM, RankedRank::IV, 10, wins: 18, losses: 14),
        ]);

        $this->assertSame(30, $series->rawProgression());
        $this->assertSame(39.0, $series->weightedProgression());
        $this->assertSame(12, $series->gamesPlayed());
    }

    public function testLesTrousDansLaSerieSontCalculesEntreSnapshotsConnus(): void
    {
        // Pas de snapshot le 4 (cron en panne, compte non classé ce jour-là...) :
        // un seul segment entre le 3 et le 5, pondéré par le tier de départ.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03 03:00', RankedTier::SILVER, RankedRank::II, 50, wins: 5, losses: 5),
            $this->snapshot('2026-08-05 03:00', RankedTier::SILVER, RankedRank::I, 20, wins: 8, losses: 9),
        ]);

        // 1050 -> 1120 : delta 70 x 1.1 (Silver)
        $this->assertSame(70, $series->rawProgression());
        $this->assertSame(77.0, $series->weightedProgression());
    }

    public function testQuaranteHuitReleveDontUnSeulAvecUnePartie(): void
    {
        // Le cœur du passage à 30 minutes : 48 relevés par jour, dont un seul
        // porte une partie. Les 47 transitions inertes ne doivent rien peser.
        $base = new \DateTimeImmutable('2026-08-03 00:00');
        $snapshots = [];

        for ($i = 0; $i < 48; $i++) {
            $joue = $i >= 30;
            $snapshots[] = new RaceSnapshot(
                $this->player(),
                $base->modify(sprintf('+%d minutes', $i * 30)),
                new RankedQueueEntity(
                    RankedRank::IV,
                    RankedTier::GOLD,
                    $joue ? 68 : 50,
                    $joue ? 11 : 10,
                    10,
                ),
            );
        }

        $series = new PlayerRaceSeries($this->player(), $snapshots);

        $this->assertSame(1, $series->gamesPlayed());
        $this->assertSame(18, $series->rawProgression());
        $this->assertSame(22.5, $series->weightedProgression()); // 18 x 1.25
        $this->assertSame(100.0, $series->winrate());
        // Départ pris juste avant la partie (le relevé de 14h30), pas à minuit.
        $this->assertSame('2026-08-03 14:30', $series->baseline()->capturedAt->format('Y-m-d H:i'));
        $this->assertSame('2026-08-03 15:00', $series->startedAt()->format('Y-m-d H:i'));
    }

    public function testLeDecayNeComptePas(): void
    {
        // Master 120 -> decay -75 -> 2 victoires (+40). Avant la règle du segment,
        // ce joueur ressortait à -35 brut / -77 pondéré : classé négatif alors
        // qu'il a gagné toutes ses parties.
        $series = new PlayerRaceSeries($this->player(), [
            $this->apex('2026-08-03 03:00', 120, wins: 40, losses: 40),
            $this->apex('2026-08-06 03:00', 45, wins: 40, losses: 40),  // decay, aucune partie
            $this->apex('2026-08-06 21:00', 85, wins: 42, losses: 40),  // 2 victoires
        ]);

        $this->assertSame(40, $series->rawProgression());
        $this->assertSame(88.0, $series->weightedProgression()); // 40 x 2.2 (Master)
        $this->assertSame(2, $series->gamesPlayed());
        // Le decay est antérieur à la première partie : il tombe hors de la
        // fenêtre de mesure du joueur, il n'a donc rien à « expliquer ».
        $this->assertSame(0, $series->offRaceDelta());
        $this->assertSame(2845, $series->baseline()->raceScore()); // Master 45, après decay
    }

    public function testLeDecayEntreDeuxSegmentsEstIsoleDansOffRaceDelta(): void
    {
        // Ici le decay tombe ENTRE deux parties : il ne compte toujours pas dans
        // la progression, mais il explique l'écart entre départ + progression et
        // le rang réel. C'est ce chiffre que le front affiche à part.
        $series = new PlayerRaceSeries($this->player(), [
            $this->apex('2026-08-03 19:00', 45, wins: 40, losses: 40),
            $this->apex('2026-08-03 21:00', 85, wins: 42, losses: 40),  // +40
            $this->apex('2026-08-04 12:00', 10, wins: 42, losses: 40),  // decay -75
            $this->apex('2026-08-05 21:00', 30, wins: 43, losses: 40),  // +20
        ]);

        $this->assertSame(60, $series->rawProgression());
        $this->assertSame(132.0, $series->weightedProgression()); // (40 + 20) x 2.2
        $this->assertSame(-75, $series->offRaceDelta());
        // Départ 2845, progression +60, arrivée 2830 : les trois ne s'additionnent
        // pas, et c'est exactement ce que offRaceDelta rend lisible.
        $this->assertSame(2845, $series->baseline()->raceScore());
        $this->assertSame(2830, $series->finish()->raceScore());
    }

    public function testUnReleveAberrantSansPartieEstIgnore(): void
    {
        // Lecture transitoire de l'API Riot : un rang fantaisiste entre deux
        // relevés. Sans la règle du segment, il laissait un résidu pondéré
        // permanent (le brut, lui, se télescopait).
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03 10:00', RankedTier::GOLD, RankedRank::II, 50, wins: 10, losses: 10),
            $this->snapshot('2026-08-03 10:30', RankedTier::EMERALD, RankedRank::I, 0, wins: 10, losses: 10),
            $this->snapshot('2026-08-03 11:00', RankedTier::GOLD, RankedRank::II, 50, wins: 10, losses: 10),
        ]);

        $this->assertSame(0, $series->rawProgression());
        $this->assertSame(0.0, $series->weightedProgression());
        $this->assertFalse($series->hasStarted());
    }

    public function testLaBaselineEstLeReleveQuiPrecedeLaPremierePartie(): void
    {
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03 10:00', RankedTier::GOLD, RankedRank::IV, 30, wins: 10, losses: 10),
            $this->snapshot('2026-08-03 10:30', RankedTier::GOLD, RankedRank::IV, 30, wins: 10, losses: 10),
            $this->snapshot('2026-08-03 11:00', RankedTier::GOLD, RankedRank::IV, 30, wins: 10, losses: 10),
            $this->snapshot('2026-08-03 11:30', RankedTier::GOLD, RankedRank::IV, 48, wins: 11, losses: 10),
        ]);

        $this->assertTrue($series->hasStarted());
        $this->assertSame('2026-08-03 11:00', $series->baseline()->capturedAt->format('Y-m-d H:i'));
        $this->assertSame('2026-08-03 11:30', $series->startedAt()->format('Y-m-d H:i'));
        $this->assertSame(18, $series->rawProgression());
    }

    public function testJoueurSansAucunePartie(): void
    {
        // Cas du report : le joueur n'a qu'un point connu, antérieur à la fenêtre.
        // Il reste présent au classement, sans progression ni winrate.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-05 03:00', RankedTier::EMERALD, RankedRank::III, 40, wins: 100, losses: 90),
        ]);

        $this->assertFalse($series->hasStarted());
        $this->assertNull($series->startedAt());
        $this->assertSame(0, $series->rawProgression());
        $this->assertSame(0.0, $series->weightedProgression());
        $this->assertSame(0, $series->gamesPlayed());
        $this->assertSame(0, $series->offRaceDelta());
        $this->assertNull($series->winrate());
        $this->assertEquals($series->baseline(), $series->finish());
    }

    public function testLeResetDeSaisonNeProduitAucunSegment(): void
    {
        // Reset Riot : wins/losses repartent de zéro. Le compteur de parties
        // diminue, donc aucun segment — et surtout aucune progression fantôme.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03 03:00', RankedTier::GOLD, RankedRank::II, 10, wins: 100, losses: 100),
            $this->snapshot('2026-08-04 03:00', RankedTier::GOLD, RankedRank::II, 30, wins: 2, losses: 3),
        ]);

        $this->assertSame(0, $series->gamesPlayed());
        $this->assertSame(0, $series->winsDelta());
        $this->assertSame(0, $series->rawProgression());
        $this->assertNull($series->winrate());
    }

    public function testWinrateEtQualification(): void
    {
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03 03:00', RankedTier::GOLD, RankedRank::II, 10, wins: 20, losses: 20),
            $this->snapshot('2026-08-09 03:00', RankedTier::GOLD, RankedRank::I, 40, wins: 28, losses: 24),
        ]);

        $this->assertSame(12, $series->gamesPlayed());
        $this->assertSame(8, $series->winsDelta());
        $this->assertSame(66.7, $series->winrate());
        $this->assertTrue($series->isQualified(5));   // seuil hebdo
        $this->assertFalse($series->isQualified(15)); // seuil mensuel
    }

    public function testLeYoYoALaFrontiereNeCoutePlusQueLesLpPerdus(): void
    {
        // Le biais assumé jusqu'ici : la montée était payée au tarif Gold (1.25)
        // et la redescente au tarif Platinum (1.4), si bien qu'un aller-retour
        // coûtait 100 points de plus qu'il ne rapportait. Le classement disait
        // au joueur que tenter la montée en fin de semaine était une erreur.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03 18:00', RankedTier::GOLD, RankedRank::I, 90, wins: 10, losses: 10),
            $this->snapshot('2026-08-03 19:00', RankedTier::PLATINUM, RankedRank::IV, 20, wins: 11, losses: 10),
            $this->snapshot('2026-08-03 20:00', RankedTier::GOLD, RankedRank::I, 75, wins: 11, losses: 11),
        ]);

        // 1590 -> 1620 -> 1575 : le brut est exactement les LP réels.
        $this->assertSame(-15, $series->rawProgression());
        // Le détour à Platinum s'annule ; ne reste que la perte de 15 LP au tarif
        // Gold, soit -18,75, arrondi à une décimale par le contrat.
        $this->assertSame(-18.8, $series->weightedProgression());
    }

    public function testUnAllerRetourRevenuAuMemeRangEstExactementNeutre(): void
    {
        // Le joueur franchit la frontière, redescend, et se retrouve au LP exact
        // d'où il est parti : sa progression pondérée est nulle, pas négative.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-03 18:00', RankedTier::GOLD, RankedRank::I, 90, wins: 10, losses: 10),
            $this->snapshot('2026-08-03 19:00', RankedTier::PLATINUM, RankedRank::IV, 20, wins: 11, losses: 10),
            $this->snapshot('2026-08-03 20:00', RankedTier::GOLD, RankedRank::I, 90, wins: 11, losses: 11),
        ]);

        $this->assertSame(0, $series->rawProgression());
        $this->assertSame(0.0, $series->weightedProgression());
        $this->assertSame(2, $series->gamesPlayed());
    }

    public function testLesSnapshotsSontReordonnesParInstant(): void
    {
        // Défensif : l'ordre d'arrivée ne doit pas changer le résultat.
        $series = new PlayerRaceSeries($this->player(), [
            $this->snapshot('2026-08-04 03:00', RankedTier::SILVER, RankedRank::I, 20, wins: 8, losses: 9),
            $this->snapshot('2026-08-03 03:00', RankedTier::SILVER, RankedRank::II, 50, wins: 5, losses: 5),
        ]);

        $this->assertSame(70, $series->rawProgression());
        $this->assertSame(7, $series->gamesPlayed());
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
        string $capturedAt,
        RankedTier $tier,
        RankedRank $division,
        int $leaguePoints,
        int $wins = 0,
        int $losses = 0,
    ): RaceSnapshot {
        return new RaceSnapshot(
            $this->player(),
            new \DateTimeImmutable($capturedAt),
            new RankedQueueEntity($division, $tier, $leaguePoints, $wins, $losses),
        );
    }

    /** Master+ : la division est ignorée par RaceScore, seuls les LP comptent. */
    private function apex(string $capturedAt, int $leaguePoints, int $wins, int $losses): RaceSnapshot
    {
        return $this->snapshot($capturedAt, RankedTier::MASTER, RankedRank::I, $leaguePoints, $wins, $losses);
    }
}
