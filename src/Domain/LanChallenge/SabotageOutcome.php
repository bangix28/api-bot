<?php

namespace App\Domain\LanChallenge;

final readonly class SabotageOutcome
{
    public function __construct(
        public PlayerRun $saboteur,
        public PlayerRun $target,
        public Sabotage $sabotage,
    ) {
    }
}
