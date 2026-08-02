<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Application\RankedRace\ComputeEventStandings\RankedRaceEventStandingsView;
use App\Application\RankedRace\ComputeStandings\WinrateStandingsView;
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
     * @param array{start: string, end: string} $window
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
        );
    }
}
