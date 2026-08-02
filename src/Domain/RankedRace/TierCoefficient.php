<?php

namespace App\Domain\RankedRace;

use App\Domain\RiotAccount\RankedTier;

/**
 * Pondération de la progression : un LP gagné en haut de l'échelle vaut plus
 * qu'un LP gagné en bas, pour que tout le monde puisse gagner la course.
 * Le coefficient appliqué à un delta quotidien est celui du tier de DÉPART
 * du jour — gère les traversées de tier sans découpage.
 */
final class TierCoefficient
{
    public static function for(RankedTier $tier): float
    {
        return match ($tier) {
            RankedTier::IRON, RankedTier::BRONZE => 1.0,
            RankedTier::SILVER => 1.1,
            RankedTier::GOLD => 1.25,
            RankedTier::PLATINUM => 1.4,
            RankedTier::EMERALD => 1.6,
            RankedTier::DIAMOND => 1.8,
            RankedTier::MASTER, RankedTier::GRANDMASTER, RankedTier::CHALLENGER => 2.2,
            // Un snapshot n'est jamais créé pour un compte non classé.
            RankedTier::UNRANKED => throw new \InvalidArgumentException(
                'Pas de coefficient pour UNRANKED : un snapshot non classé ne devrait pas exister'
            ),
        };
    }
}
