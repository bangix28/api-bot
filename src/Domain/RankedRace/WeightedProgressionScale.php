<?php

namespace App\Domain\RankedRace;

use App\Domain\RiotAccount\RankedTier;

/**
 * Convertit une position de l'échelle de course en points de course, en payant
 * chaque LP au tarif du palier où il a été gagné.
 *
 * Le delta pondéré d'un segment est la différence de deux conversions, ce qui
 * donne trois propriétés que le tarif du palier de DÉPART n'offrait pas :
 *
 * - un aller-retour à la frontière d'un palier vaut exactement zéro ;
 * - la pondération ne dépend que des deux positions, jamais du chemin ni de
 *   l'ordre des segments ;
 * - un segment traversant une frontière est découpé au pro-rata, au lieu d'être
 *   payé en entier au tarif du palier de départ.
 *
 * Avant, une montée Gold -> Platinum était payée à 1.25 et la redescente à 1.4 :
 * un aller-retour coûtait plus qu'il ne rapportait, et le classement disait au
 * joueur que tenter la montée en fin de semaine était une mauvaise idée.
 *
 * TierCoefficient devient la pente de cette conversion sur la bande du palier.
 */
final class WeightedProgressionScale
{
    /**
     * Les bandes, dans l'ordre de l'échelle. MASTER porte la bande apex, que ses
     * trois libellés partagent et qui n'a pas de plafond.
     */
    private const array BANDS = [
        RankedTier::IRON,
        RankedTier::BRONZE,
        RankedTier::SILVER,
        RankedTier::GOLD,
        RankedTier::PLATINUM,
        RankedTier::EMERALD,
        RankedTier::DIAMOND,
        RankedTier::MASTER,
    ];

    /**
     * Tout le calcul se fait en centièmes de point, en entiers : les tarifs n'ont
     * jamais plus de deux décimales, et la conversion reste ainsi exacte. Sommer
     * puis soustraire des flottants faisait valoir 2,2000000000003 point à un LP
     * apex, et un aller-retour n'était plus rigoureusement nul.
     */
    private const int HUNDREDTHS = 100;

    /** Points de course accumulés depuis Iron IV 0 LP jusqu'à cette position. */
    public static function at(int $raceScore): float
    {
        return self::hundredthsAt($raceScore) / self::HUNDREDTHS;
    }

    /** Points gagnés en passant d'une position à l'autre, négatifs si l'on recule. */
    public static function deltaBetween(int $from, int $to): float
    {
        return (self::hundredthsAt($to) - self::hundredthsAt($from)) / self::HUNDREDTHS;
    }

    private static function hundredthsAt(int $raceScore): int
    {
        if ($raceScore < 0) {
            throw new \InvalidArgumentException(
                sprintf('Position hors échelle : %d, Iron IV 0 LP est le plancher', $raceScore)
            );
        }

        $hundredths = 0;

        foreach (self::BANDS as $tier) {
            $floor = $tier->index() * RaceScore::LP_PER_TIER;

            if ($raceScore <= $floor) {
                break;
            }

            $ceiling = $tier->isApex() ? $raceScore : min($raceScore, $floor + RaceScore::LP_PER_TIER);
            $hundredths += ($ceiling - $floor) * (int) round(TierCoefficient::for($tier) * self::HUNDREDTHS);
        }

        return $hundredths;
    }
}
