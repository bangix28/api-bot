<?php

namespace App\Application\RankedRace\ComputeStandings;

readonly class ProgressionEntryView
{
    public function __construct(
        public string $riotId,
        public string $summonerName,
        public string $logoId,
        public RankSnapshotView $start,
        public RankSnapshotView $end,
        public int $rawDelta,
        public float $weightedDelta,
        public int $rankRaw,
        public int $rankWeighted,
        public int $gamesPlayed,
        public ?float $winrate,
        /**
         * LP gagnés ou perdus HORS jeu entre le départ et l'arrivée : decay,
         * corrections de MMR. Exposé à part parce que « départ + progression »
         * n'égale pas le rang d'arrivée — trois faits distincts, pas une somme.
         */
        public int $offRaceDelta = 0,
        /** Instant où la course a démarré pour ce joueur, null s'il n'a pas joué. */
        public ?string $startedAt = null,
        /** Dernier relevé ayant constaté une partie, null s'il n'a pas joué. */
        public ?string $lastActivityAt = null,
    ) {
    }
}
