<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\RankedRace;
use App\Application\RankedRace\ComputeStandings\ComputeRankedRaceStandingsCommand;
use App\Application\RankedRace\ComputeStandings\ComputeRankedRaceStandingsHandler;

/**
 * Adaptateur HTTP de la Ranked Race (pendant "lecture" des controllers minces) :
 * construit le Command depuis la query string, délègue au Handler, mappe la vue.
 * Les paramètres invalides lèvent InvalidRankedRaceParameterException -> 400.
 */
class RankedRaceProvider implements ProviderInterface
{
    public function __construct(private readonly ComputeRankedRaceStandingsHandler $handler)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RankedRace
    {
        $filters = $context['filters'] ?? [];

        $view = $this->handler->handle(new ComputeRankedRaceStandingsCommand(
            (string) ($filters['queue'] ?? 'solo'),
            (string) ($filters['period'] ?? 'week'),
        ));

        return RankedRace::fromView($view);
    }
}
