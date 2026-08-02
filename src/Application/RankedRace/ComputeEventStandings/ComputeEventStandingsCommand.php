<?php

namespace App\Application\RankedRace\ComputeEventStandings;

final readonly class ComputeEventStandingsCommand
{
    public function __construct(public int $eventId)
    {
    }
}
