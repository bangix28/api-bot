<?php

namespace App\Application\LanChallenge\PickCard;

final readonly class PickCardCommand
{
    public function __construct(
        public int $lanId,
        public string $puuid,
        /** Emplacement de la carte dans l'offre, de 0 à 2. */
        public int $slot,
    ) {
    }
}
