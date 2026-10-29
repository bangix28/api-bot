<?php

namespace App\Application\LanChallenge\GetStandings;

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
        /** Le joueur a une carte pour la prochaine partie, ou en jeu. Laquelle, le classement ne le dit jamais. */
        public bool $cardChosen,
        public bool $cardLocked,
    ) {
    }
}
