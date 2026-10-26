<?php

namespace App\Domain\LanGame\Event;

final readonly class GameEnd implements ObservedEvent
{
    public function __construct(
        /** Résultat vu du joueur du poste : true pour « Win », false pour « Lose », null sinon. */
        public ?bool $activePlayerWon,
    ) {
    }
}
