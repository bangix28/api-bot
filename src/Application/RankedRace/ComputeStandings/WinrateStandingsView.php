<?php

namespace App\Application\RankedRace\ComputeStandings;

readonly class WinrateStandingsView
{
    /**
     * @param WinrateEntryView[] $qualified
     * @param WinrateEntryView[] $notQualified affichés grisés avec leur compteur « x/N parties »
     */
    public function __construct(
        public int $gamesRequired,
        public array $qualified,
        public array $notQualified,
    ) {
    }
}
