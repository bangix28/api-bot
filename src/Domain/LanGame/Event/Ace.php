<?php

namespace App\Domain\LanGame\Event;

final readonly class Ace implements ObservedEvent
{
    public function __construct(
        /** Pseudo du joueur qui a porté le dernier coup de l'Ace. */
        public string $acerName,
    ) {
    }
}
