<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\RankedRaceEvent;
use App\Application\RankedRace\ListEvents\ListRankedRaceEventsHandler;

class RankedRaceEventCollectionProvider implements ProviderInterface
{
    public function __construct(private readonly ListRankedRaceEventsHandler $handler)
    {
    }

    /** @return RankedRaceEvent[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(RankedRaceEvent::fromView(...), $this->handler->handle());
    }
}
