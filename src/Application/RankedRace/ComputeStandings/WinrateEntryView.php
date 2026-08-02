<?php

namespace App\Application\RankedRace\ComputeStandings;

readonly class WinrateEntryView
{
    public function __construct(
        public string $riotId,
        public string $summonerName,
        public string $logoId,
        public int $wins,
        public int $losses,
        public int $gamesPlayed,
        public ?float $winrate,
    ) {
    }
}
