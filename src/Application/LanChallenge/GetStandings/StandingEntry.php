<?php

namespace App\Application\LanChallenge\GetStandings;

use App\Domain\LanChallenge\Challenge;

/** La ligne d'un participant au classement de la LAN. */
final readonly class StandingEntry
{
    public function __construct(
        public string $riotId,
        /** false : participant de la LAN qui n'a pas encore rejoint les défis. */
        public bool $joined,
        public int $score,
        public int $successes,
        public int $roundsPlayed,
        public int $rerollsLeft,
        /** Carte choisie pour la prochaine partie, ou en jeu ; null sinon. */
        public ?Challenge $activeCard,
        public bool $cardLocked,
    ) {
    }
}
