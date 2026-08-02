<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\RankedRaceEventStandings;
use App\Application\RankedRace\ComputeEventStandings\ComputeEventStandingsCommand;
use App\Application\RankedRace\ComputeEventStandings\ComputeEventStandingsHandler;

/**
 * Un événement inconnu lève RaceEventNotFoundException -> 404 (api_platform.yaml).
 */
class RankedRaceEventStandingsProvider implements ProviderInterface
{
    public function __construct(private readonly ComputeEventStandingsHandler $handler)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RankedRaceEventStandings
    {
        return RankedRaceEventStandings::fromView(
            $this->handler->handle(new ComputeEventStandingsCommand((int) $uriVariables['id']))
        );
    }
}
