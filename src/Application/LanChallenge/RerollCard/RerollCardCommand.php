<?php

namespace App\Application\LanChallenge\RerollCard;

final readonly class RerollCardCommand
{
    public function __construct(
        public int $lanId,
        public string $puuid,
        /** Emplacement de la carte à remplacer, de 0 à 2. */
        public int $slot,
    ) {
    }
}
