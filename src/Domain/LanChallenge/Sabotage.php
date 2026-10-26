<?php

namespace App\Domain\LanChallenge;

/** Une carte qui vise un joueur, jouée par un saboteur contre une cible. */
final readonly class Sabotage
{
    public function __construct(
        public Challenge $card,
        public string $saboteurPuuid,
        public string $targetPuuid,
    ) {
        if (!$card->targetsPlayer()) {
            throw new \InvalidArgumentException(sprintf('La carte %s ne vise pas un joueur.', $card->value));
        }
    }

    /**
     * Le fil d'exploits annonce un sabotage visible (un vol de relance se
     * remarque), jamais une Malédiction : la cible ne doit pas la connaître.
     */
    public function isVisibleToTarget(): bool
    {
        return $this->card !== Challenge::CURSE;
    }
}
