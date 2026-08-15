<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Application\RankedRace\ComputeEventStandings\RankedRaceEventStandingsView;
use App\Application\RankedRace\ComputeStandings\WinrateStandingsView;
use App\Domain\RankedRace\RaceFreshness;
use App\State\RankedRaceEventStandingsProvider;

/**
 * Classements d'un événement Ranked Race : mêmes shapes que /ranked-race
 * (progression brute + pondérée, winrate avec seuil propre à l'événement).
 */
#[ApiResource(
    shortName: 'RankedRaceEventStandings',
    operations: [
        new Get(
            uriTemplate: '/ranked-race-events/{id}',
            requirements: ['id' => '\d+'],
            openapi: new \ApiPlatform\OpenApi\Model\Operation(
                summary: 'Classements d\'un événement Ranked Race',
            ),
            provider: RankedRaceEventStandingsProvider::class,
        ),
    ],
)]
class RankedRaceEventStandings
{
    /**
     * @param array{start: string, end: string} $window bornes au jour, dernier jour INCLUS
     * @param array{start: string, endExclusive: string} $windowIso ISO-8601 avec décalage, fin EXCLUE
     * @param array{snapshotAgeSeconds: int|null, snapshotIntervalSeconds: int} $staleness
     * @param \App\Application\RankedRace\ComputeStandings\ProgressionEntryView[] $progression
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $queue,
        public array $window,
        public string $status,
        public int $minGamesToQualify,
        public bool $progressionSuspended,
        public array $progression,
        public WinrateStandingsView $winrate,
        public string $raceStatus,
        public array $windowIso,
        public ?string $lastSnapshotAt,
        public ?string $nextRefreshAt,
        public ?string $generatedAt,
        public array $staleness,
    ) {
    }

    public static function fromView(RankedRaceEventStandingsView $view): self
    {
        return new self(
            $view->id,
            $view->name,
            $view->queue,
            ['start' => $view->windowStart, 'end' => $view->windowEnd],
            $view->status,
            $view->minGamesToQualify,
            $view->progressionSuspended,
            $view->progression,
            $view->winrate,
            $view->raceStatus,
            $view->windowIso,
            $view->lastSnapshotAt,
            $view->nextRefreshAt,
            $view->generatedAt,
            [
                'snapshotAgeSeconds' => $view->snapshotAgeSeconds,
                'snapshotIntervalSeconds' => RaceFreshness::SNAPSHOT_INTERVAL_SECONDS,
            ],
        );
    }
}
