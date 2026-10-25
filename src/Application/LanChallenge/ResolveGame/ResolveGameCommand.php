<?php

namespace App\Application\LanChallenge\ResolveGame;

use App\Domain\LanChallenge\GameReport;

final readonly class ResolveGameCommand
{
    public function __construct(
        public int $lanId,
        public GameReport $report,
    ) {
    }
}
