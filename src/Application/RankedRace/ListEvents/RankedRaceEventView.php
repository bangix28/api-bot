<?php

namespace App\Application\RankedRace\ListEvents;

readonly class RankedRaceEventView
{
    public function __construct(
        public int $id,
        public string $name,
        public string $queue,
        public string $windowStart,
        public string $windowEnd,
        public string $status,
        public int $minGamesToQualify,
    ) {
    }
}
