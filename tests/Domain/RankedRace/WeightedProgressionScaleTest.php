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
    public function testLesPlanchersDePalierSontFigesParLaTableDesTarifs(): void
    {
        // Chaque plancher = le précédent + 400 LP au tarif du palier traversé.
        //   Iron 0       Bronze 400 (+400 x1.0)   Silver 800 (+400 x1.0)
        //   Gold 1240 (+400 x1.1)                 Platinum 1740 (+400 x1.25)
        //   Emerald 2300 (+400 x1.4)              Diamond 2940 (+400 x1.6)
        //   Master 3660 (+400 x1.8), puis x2.2 sans plafond
        $this->assertSame(0.0, WeightedProgressionScale::at(0));
        $this->assertSame(400.0, WeightedProgressionScale::at(400));
        $this->assertSame(800.0, WeightedProgressionScale::at(800));
        $this->assertSame(1240.0, WeightedProgressionScale::at(1200));
        $this->assertSame(1740.0, WeightedProgressionScale::at(1600));
        $this->assertSame(2300.0, WeightedProgressionScale::at(2000));
        $this->assertSame(2940.0, WeightedProgressionScale::at(2400));
        $this->assertSame(3660.0, WeightedProgressionScale::at(2800));
    }

    public function testLaBandeApexNAPasDePlafond(): void
    {
        // Un joueur Challenger à 1 200 LP reste sur la pente Master :
        // 3 660 + 1 200 x 2.2 = 6 300.
        $this->assertSame(6300.0, WeightedProgressionScale::at(2800 + 1200));
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
        // Gold I 80 (1580) -> Platinum IV 10 (1610) : 20 LP en Gold à 1.25 (25)
        // puis 10 LP en Platinum à 1.4 (14), soit 39. Au tarif du seul palier de
        // départ, ces 30 LP valaient 37,5.
        $from = $this->score(RankedTier::GOLD, RankedRank::I, 80);
        $to = $this->score(RankedTier::PLATINUM, RankedRank::IV, 10);

        $this->assertSame(39.0, WeightedProgressionScale::deltaBetween($from, $to));
    }

    public function testLaFrontiereApexEstTraverseeSansDiscontinuite(): void
    {
        // Diamond I 90 (2790) -> Master 30 (2830) : 10 LP en Diamond à 1.8 (18)
        // puis 30 LP en apex à 2.2 (66), soit 84.
        $from = $this->score(RankedTier::DIAMOND, RankedRank::I, 90);
        $to = $this->score(RankedTier::MASTER, RankedRank::UNRANKED, 30);

        $this->assertSame(84.0, WeightedProgressionScale::deltaBetween($from, $to));
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

        // Master 400 LP : 3 660 + 400 x 2.2 = 4 540.
        $this->assertSame(4540.0, WeightedProgressionScale::at(3200));
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
        // Le calcul passe par des centièmes entiers : le tarif 1.25 produit des
        // quarts de point qui doivent tomber juste, et un aller-retour apex —
        // là où les valeurs sont les plus grandes — doit rendre zéro strict.
        $this->assertSame(1.25, WeightedProgressionScale::deltaBetween(1200, 1201));
        $this->assertSame(6.25, WeightedProgressionScale::deltaBetween(1200, 1205));
        $this->assertSame(12.5, WeightedProgressionScale::deltaBetween(1200, 1210));

        $apexBas = $this->score(RankedTier::MASTER, RankedRank::UNRANKED, 150);
        $apexHaut = $this->score(RankedTier::MASTER, RankedRank::UNRANKED, 900);

        $this->assertSame(1650.0, WeightedProgressionScale::deltaBetween($apexBas, $apexHaut));
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
