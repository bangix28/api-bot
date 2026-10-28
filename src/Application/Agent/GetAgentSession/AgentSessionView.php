<?php

namespace App\Application\Agent\GetAgentSession;

use App\Domain\LanChallenge\LanSummary;

final readonly class AgentSessionView
{
    /**
     * @param string[] $riotIds comptes Riot liés au joueur
     */
    public function __construct(
        public array $riotIds,
        /** Ses files acceptées ($lan->acceptedQueues) disent à l'agent quelles parties envoyer. */
        public LanSummary $lan,
        /** false : la LAN n'a pas commencé, l'agent n'envoie rien. */
        public bool $lanInProgress,
        public int $reportIntervalSeconds,
    ) {
    }
}
