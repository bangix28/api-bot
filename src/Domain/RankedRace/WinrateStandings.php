<?php

namespace App\Domain\RankedRace;

readonly class WinrateStandings
{
    /**
     * @param PlayerRaceSeries[] $qualified    triés : winrate desc, puis wins, puis parties
     * @param PlayerRaceSeries[] $notQualified sous le seuil, triés par parties jouées desc
     */
    public function __construct(
        public array $qualified,
        public array $notQualified,
    ) {
    }
}
