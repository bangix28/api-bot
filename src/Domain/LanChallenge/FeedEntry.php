<?php

namespace App\Domain\LanChallenge;

/** Une manche réglée, telle qu'elle apparaît dans le fil d'exploits de la LAN. */
final readonly class FeedEntry
{
    public function __construct(
        public string $riotId,
        public string $gameId,
        public Challenge $card,
        public Verdict $verdict,
        public int $points,
        public bool $roundBonus,
        public \DateTimeImmutable $settledAt,
    ) {
    }
}
