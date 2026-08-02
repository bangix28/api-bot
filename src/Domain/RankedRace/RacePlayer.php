<?php

namespace App\Domain\RankedRace;

/** Identité d'affichage d'un participant, jointe au chargement des snapshots. */
readonly class RacePlayer
{
    public function __construct(
        public string $riotId,
        public string $summonerName,
        public string $logoId,
    ) {
    }
}
