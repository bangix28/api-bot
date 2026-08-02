<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Application\RankedRace\ComputeStandings\RankedRaceStandingsView;
use App\Application\RankedRace\ComputeStandings\WinrateStandingsView;
use App\State\RankedRaceProvider;

/**
 * Course au classement : compétition sur la période calendaire courante,
 * calculée à la volée depuis les snapshots elo quotidiens.
 * Ressource sans persistance : les données viennent du provider.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/ranked-race',
            openapi: new \ApiPlatform\OpenApi\Model\Operation(
                summary: 'Classements de la Ranked Race',
                description: 'Progression brute et pondérée + winrate de période, pour la file et la période demandées.',
            ),
            provider: RankedRaceProvider::class,
            parameters: [
                'queue' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['solo', 'flex'], 'default' => 'solo']),
                'period' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['week', 'month'], 'default' => 'week']),
            ],
        ),
    ],
)]
class RankedRace
{
    /**
     * @param array{start: string, end: string} $window
     * @param \App\Application\RankedRace\ComputeStandings\ProgressionEntryView[] $progression
     */
    public function __construct(
        public string $queue,
        public string $period,
        public array $window,
        public bool $progressionSuspended,
        public array $progression,
        public WinrateStandingsView $winrate,
    ) {
    }

    public static function fromView(RankedRaceStandingsView $view): self
    {
        return new self(
            $view->queue,
            $view->period,
            ['start' => $view->windowStart, 'end' => $view->windowEnd],
            $view->progressionSuspended,
            $view->progression,
            $view->winrate,
        );
    }
}
