<?php

namespace App\Domain\RankedRace;

/**
 * Ligne du classement Progression : la série d'un joueur avec ses deux rangs
 * (brut et pondéré), pour un affichage côte à côte sans re-tri côté front.
 */
readonly class ProgressionStanding
{
    public function __construct(
        public PlayerRaceSeries $series,
        public int $rankRaw,
        public int $rankWeighted,
    ) {
    }
}
