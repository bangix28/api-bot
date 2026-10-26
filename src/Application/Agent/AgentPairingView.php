<?php

namespace App\Application\Agent;

use App\Domain\LanChallenge\LanSummary;

/** Le joueur et la LAN auxquels un agent est appairé. */
final readonly class AgentPairingView
{
    /**
     * @param string[] $riotIds comptes Riot liés au joueur
     */
    public function __construct(
        public LanSummary $lan,
        public array $riotIds,
    ) {
    }
}
