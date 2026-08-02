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
    ) {
    }
}
