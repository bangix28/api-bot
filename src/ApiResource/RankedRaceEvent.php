<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Application\RankedRace\ListEvents\RankedRaceEventView;
use App\State\RankedRaceEventCollectionProvider;

/**
 * Liste des événements Ranked Race (passés, actifs, à venir) — méta seulement,
 * les classements se chargent via /ranked-race-events/{id}.
 */
#[ApiResource(
    shortName: 'RankedRaceEvent',
    operations: [
        new GetCollection(
            uriTemplate: '/ranked-race-events',
            paginationEnabled: false,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(
                summary: 'Liste des événements Ranked Race',
                description: 'Tous les événements (passés, actifs, à venir) avec leur statut calculé.',
            ),
            provider: RankedRaceEventCollectionProvider::class,
        ),
    ],
)]
class RankedRaceEvent
{
    /** @param array{start: string, end: string} $window */
    public function __construct(
        public int $id,
        public string $name,
        public string $queue,
        public array $window,
        public string $status,
        public int $minGamesToQualify,
    ) {
    }

    public static function fromView(RankedRaceEventView $view): self
    {
        return new self(
            $view->id,
            $view->name,
            $view->queue,
            ['start' => $view->windowStart, 'end' => $view->windowEnd],
            $view->status,
            $view->minGamesToQualify,
        );
    }
}
