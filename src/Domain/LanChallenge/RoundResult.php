<?php

namespace App\Domain\LanChallenge;

/** Issue d'une manche pour un joueur : la carte jouée, le verdict, les points. */
final readonly class RoundResult
{
    public function __construct(
        public string $gameId,
        public Challenge $card,
        public Verdict $verdict,
        public int $points,
        public bool $roundBonus,
    ) {
    }
}
