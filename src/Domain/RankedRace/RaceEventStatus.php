<?php

namespace App\Domain\RankedRace;

enum RaceEventStatus: string
{
    case UPCOMING = 'upcoming';
    case ACTIVE = 'active';
    case FINISHED = 'finished';
}
