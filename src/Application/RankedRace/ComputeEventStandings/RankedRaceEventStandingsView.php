<?php

namespace App\Application\RankedRace\ComputeEventStandings;

use App\Application\RankedRace\ComputeStandings\WinrateStandingsView;

readonly class RankedRaceEventStandingsView
{
    /** @param \App\Application\RankedRace\ComputeStandings\ProgressionEntryView[] $progression */
    public function __construct(
        public int $id,
        public string $name,
        public string $queue,
        public string $windowStart,
        public string $windowEnd,
        public string $status,
        public int $minGamesToQualify,
        public bool $progressionSuspended,
        public array $progression,
        public WinrateStandingsView $winrate,
    ) {
    }
}
