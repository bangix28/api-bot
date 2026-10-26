<?php

namespace App\Domain\LanGame;

/** Un des 10 joueurs de la partie, tel que le relevé le décrit (`allPlayers`). */
final readonly class ObservedPlayer
{
    public function __construct(
        /** Riot ID complet, « Pseudo#TAG ». */
        public string $riotId,
        /** Pseudo seul, tel que les événements le citent. */
        public string $gameName,
        /** ORDER ou CHAOS. */
        public string $team,
        public int $kills,
        public int $deaths,
        public int $assists,
    ) {
    }
}
