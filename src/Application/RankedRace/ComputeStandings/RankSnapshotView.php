<?php

namespace App\Application\RankedRace\ComputeStandings;

/** Rang d'un joueur en début ou fin de période. */
readonly class RankSnapshotView
{
    public function __construct(
        public string $tier,
        public string $division,
        public int $leaguePoints,
        public int $raceScore,
    ) {
    }
}
