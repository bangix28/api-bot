<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\RankedRace\RaceScore;
use App\Domain\RankedRace\TierCoefficient;
use App\Domain\RankedRace\WeightedProgressionScale;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use PHPUnit\Framework\TestCase;

class WeightedProgressionScaleTest extends TestCase
{
    public function testLaConversionEstLIdentiteJusquAPlatine(): void
    {
        // Tarif plat à 1.0 d'Iron à Platine : sous Emerald, un LP vaut un point,
        // et les deux classements affichent le même chiffre. C'est voulu — la
        // pondération ne corrige que les frictions du haut de l'échelle.
        $this->assertSame(0.0, WeightedProgressionScale::at(0));
        $this->assertSame(400.0, WeightedProgressionScale::at(400));
        $this->assertSame(800.0, WeightedProgressionScale::at(800));
        $this->assertSame(1200.0, WeightedProgressionScale::at(1200));
        $this->assertSame(1600.0, WeightedProgressionScale::at(1600));
        $this->assertSame(2000.0, WeightedProgressionScale::at(2000));
    }

    public function testLesPlanchersDuHautDeLEchelleSontFigesParLaTableDesTarifs(): void
    {
        // Emerald 2000 (fin de l'identité), puis :
        //   Diamond 2420 (+400 x1.05)   Master 2880 (+400 x1.15)
        //   au-delà : x1.35 sans plafond
        $this->assertSame(2000.0, WeightedProgressionScale::at(2000));
        $this->assertSame(2420.0, WeightedProgressionScale::at(2400));
        $this->assertSame(2880.0, WeightedProgressionScale::at(2800));
    }

    public function testLaBandeApexNAPasDePlafond(): void
    {
        // Un joueur Challenger à 1 200 LP reste sur la pente Master :
        // 2 880 + 1 200 x 1.35 = 4 500.
        $this->assertSame(4500.0, WeightedProgressionScale::at(2800 + 1200));
    }

    public function testUnAllerRetourALaFrontiereEstExactementNeutre(): void
    {
        // La propriété qui manquait : monter puis redescendre au même LP ne coûte
        // plus rien. Avant, la montée était payée au tarif du palier bas et la
        // redescente au tarif du palier haut.
        $goldOne90 = $this->score(RankedTier::GOLD, RankedRank::I, 90);
        $platinumFour20 = $this->score(RankedTier::PLATINUM, RankedRank::IV, 20);

        $monte = WeightedProgressionScale::deltaBetween($goldOne90, $platinumFour20);
        $redescend = WeightedProgressionScale::deltaBetween($platinumFour20, $goldOne90);

        $this->assertSame(0.0, $monte + $redescend);
    }

    public function testUnDeltaTraversantUneFrontiereEstDecoupeAuProRata(): void
    {
        // Emerald I 80 (2380) -> Diamond IV 10 (2410) : 20 LP en Emerald à 1.05
        // (21) puis 10 LP en Diamond à 1.15 (11,5), soit 32,5. Au tarif du seul
        // palier de départ, ces 30 LP vaudraient 31,5.
        $from = $this->score(RankedTier::EMERALD, RankedRank::I, 80);
        $to = $this->score(RankedTier::DIAMOND, RankedRank::IV, 10);

        $this->assertSame(32.5, WeightedProgressionScale::deltaBetween($from, $to));
    }

    public function testLaFrontiereApexEstTraverseeSansDiscontinuite(): void
    {
        // Diamond I 90 (2790) -> Master 30 (2830) : 10 LP en Diamond à 1.15
        // (11,5) puis 30 LP en apex à 1.35 (40,5), soit 52.
        $from = $this->score(RankedTier::DIAMOND, RankedRank::I, 90);
        $to = $this->score(RankedTier::MASTER, RankedRank::UNRANKED, 30);

        $this->assertSame(52.0, WeightedProgressionScale::deltaBetween($from, $to));
    }

    public function testChaqueLpVautExactementLeTarifDeSonPalier(): void
    {
        // Balayage exhaustif jusqu'à Master 400 LP : gagner un LP rapporte
        // toujours le tarif de son palier, jamais moins que le plus petit ni plus
        // que le plus grand de la table.
        for ($score = 1; $score <= 3200; $score++) {
            $step = WeightedProgressionScale::deltaBetween($score - 1, $score);

            if ($step < TierCoefficient::lowest() || $step > TierCoefficient::highest()) {
                $this->fail(sprintf(
                    'Le LP %d vaut %s point(s), hors de la plage des tarifs [%s ; %s]',
                    $score,
                    $step,
                    TierCoefficient::lowest(),
                    TierCoefficient::highest(),
                ));
            }
        }

        // Master 400 LP : 2 880 + 400 x 1.35 = 3 420.
        $this->assertSame(3420.0, WeightedProgressionScale::at(3200));
    }

    public function testLaPonderationNeDependQueDesDeuxPositions(): void
    {
        // Indépendance du chemin : peu importe par où l'on passe, seuls comptent
        // le point de départ et le point d'arrivée.
        $depart = $this->score(RankedTier::EMERALD, RankedRank::II, 50);
        $arrivee = $this->score(RankedTier::DIAMOND, RankedRank::III, 20);
        $detour = $this->score(RankedTier::PLATINUM, RankedRank::I, 10);

        $direct = WeightedProgressionScale::deltaBetween($depart, $arrivee);
        $parLeBas = WeightedProgressionScale::deltaBetween($depart, $detour)
            + WeightedProgressionScale::deltaBetween($detour, $arrivee);

        $this->assertSame($direct, $parLeBas);
    }

    public function testUnePositionNegativeEstRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WeightedProgressionScale::at(-1);
    }

    public function testLaConversionEstExacteAuCentiemeSansDeriveFlottante(): void
    {
        // Le calcul passe par des centièmes entiers : les tarifs 1.05 et 1.15
        // produisent des centièmes qui doivent tomber juste, et un aller-retour
        // apex — là où les valeurs sont les plus grandes — doit rendre zéro strict.
        $this->assertSame(1.05, WeightedProgressionScale::deltaBetween(2000, 2001));
        $this->assertSame(10.5, WeightedProgressionScale::deltaBetween(2000, 2010));
        $this->assertSame(1.15, WeightedProgressionScale::deltaBetween(2400, 2401));
        $this->assertSame(8.05, WeightedProgressionScale::deltaBetween(2400, 2407));

        $apexBas = $this->score(RankedTier::MASTER, RankedRank::UNRANKED, 150);
        $apexHaut = $this->score(RankedTier::MASTER, RankedRank::UNRANKED, 900);

        $this->assertSame(1012.5, WeightedProgressionScale::deltaBetween($apexBas, $apexHaut));
        $this->assertSame(
            0.0,
            WeightedProgressionScale::deltaBetween($apexBas, $apexHaut)
            + WeightedProgressionScale::deltaBetween($apexHaut, $apexBas),
        );
    }

    private function score(RankedTier $tier, RankedRank $division, int $leaguePoints): int
    {
        return RaceScore::of(new RankedQueueEntity($division, $tier, $leaguePoints, 0, 0));
    }
}
