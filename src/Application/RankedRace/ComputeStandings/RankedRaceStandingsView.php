<?php

namespace App\Application\RankedRace\ComputeStandings;

readonly class RankedRaceStandingsView
{
    /** @param ProgressionEntryView[] $progression vide si la course Progression est suspendue */
    public function __construct(
        public string $queue,
        public string $period,
        public string $windowStart,
        public string $windowEnd,
        public bool $progressionSuspended,
        public array $progression,
        public WinrateStandingsView $winrate,
    ) {
    }
}
