<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\RankedRace\RaceEvent;
use App\Domain\RankedRace\RaceEventRepositoryInterface;

final readonly class InMemoryRaceEventRepository implements RaceEventRepositoryInterface
{
    /** @param RaceEvent[] $events */
    public function __construct(private array $events = [])
    {
    }

    public function findAll(): array
    {
        $sorted = $this->events;
        usort($sorted, static fn(RaceEvent $a, RaceEvent $b) => $b->window->start <=> $a->window->start);

        return $sorted;
    }

    public function findById(int $id): ?RaceEvent
    {
        foreach ($this->events as $event) {
            if ($event->id === $id) {
                return $event;
            }
        }

        return null;
    }
}
