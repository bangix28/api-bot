<?php

namespace App\Application\LanChallenge\GetMyRun;

use App\Domain\LanChallenge\LanSummary;
use App\Domain\LanChallenge\PlayerRun;

final readonly class MyRunView
{
    public function __construct(
        public LanSummary $lan,
        public string $riotId,
        /** null tant que le joueur n'a pas rejoint les défis. */
        public ?PlayerRun $run,
    ) {
    }
}
