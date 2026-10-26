<?php

namespace App\Application\LanChallenge\GetStandings;

use App\Domain\LanChallenge\LanSummary;

final readonly class StandingsView
{
    /**
     * @param StandingEntry[] $entries du premier au dernier
     */
    public function __construct(
        public LanSummary $lan,
        public array $entries,
    ) {
    }
}
