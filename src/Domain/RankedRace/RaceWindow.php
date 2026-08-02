<?php

namespace App\Domain\RankedRace;

readonly class RaceWindow
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {
    }
}
