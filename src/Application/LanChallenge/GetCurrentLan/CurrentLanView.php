<?php

namespace App\Application\LanChallenge\GetCurrentLan;

use App\Domain\LanChallenge\LanSummary;

final readonly class CurrentLanView
{
    /** @param string[] $riotIds comptes du joueur inscrits à cette LAN */
    public function __construct(
        public LanSummary $lan,
        public bool $inProgress,
        public array $riotIds,
    ) {
    }
}
