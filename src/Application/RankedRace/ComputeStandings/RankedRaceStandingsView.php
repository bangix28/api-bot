<?php

namespace App\Application\RankedRace\ComputeStandings;

readonly class RankedRaceStandingsView
{
    /**
     * @param ProgressionEntryView[] $progression vide si la course Progression est suspendue
     * @param array{start: string, endExclusive: string} $windowIso
     */
    public function __construct(
        public string $queue,
        public string $period,
        public string $windowStart,
        public string $windowEnd,
        public bool $progressionSuspended,
        public array $progression,
        public WinrateStandingsView $winrate,
        public string $raceStatus = 'running',
        public array $windowIso = ['start' => '', 'endExclusive' => ''],
        public ?string $lastSnapshotAt = null,
        public ?string $nextRefreshAt = null,
        public ?string $generatedAt = null,
        public ?int $snapshotAgeSeconds = null,
    ) {
    }
}
