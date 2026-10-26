<?php

namespace App\Domain\LanGame\Event;

final readonly class Multikill implements ObservedEvent
{
    public function __construct(
        public string $killerName,
        /** 2 = doublé … 5 = penta. */
        public int $killStreak,
    ) {
    }
}
