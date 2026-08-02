<?php

namespace App\Application\RankedRace\ListEvents;

use App\Domain\RankedRace\RaceEvent;
use App\Domain\RankedRace\RaceEventRepositoryInterface;
use App\Domain\Shared\ClockInterface;

/**
 * Liste tous les événements (passés, actifs, à venir) avec leur statut calculé.
 * Les événements terminés restent listés : leur palmarès est consultable.
 */
final readonly class ListRankedRaceEventsHandler
{
    public function __construct(
        private RaceEventRepositoryInterface $events,
        private ClockInterface $clock,
    ) {
    }

    /** @return RankedRaceEventView[] */
    public function handle(): array
    {
        $today = $this->clock->today();

        return array_map(
            static fn(RaceEvent $event) => new RankedRaceEventView(
                $event->id,
                $event->name,
                $event->queue->toQueryParam(),
                $event->window->start->format('Y-m-d'),
                $event->window->end->format('Y-m-d'),
                $event->statusAt($today)->value,
                $event->minGamesToQualify,
            ),
            $this->events->findAll(),
        );
    }
}
