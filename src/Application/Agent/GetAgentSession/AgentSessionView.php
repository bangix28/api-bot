<?php

namespace App\Application\Agent\GetAgentSession;

use App\Domain\LanChallenge\LanSummary;

final readonly class AgentSessionView
{
    /**
     * @param string[] $riotIds comptes Riot liés au joueur
     * @param string[] $acceptedGameModes
     */
    public function __construct(
        public array $riotIds,
        public LanSummary $lan,
        /** false : la LAN n'a pas commencé, l'agent n'envoie rien. */
        public bool $lanInProgress,
        public array $acceptedGameModes,
        public int $reportIntervalSeconds,
    ) {
    }
}
