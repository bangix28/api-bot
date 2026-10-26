<?php

namespace App\Domain\Agent;

interface AgentTokenRepositoryInterface
{
    /** Enregistre le jeton et révoque les précédents du même joueur pour la même LAN. */
    public function saveReplacingPrevious(AgentToken $token): void;

    /** Jeton non révoqué correspondant à cette valeur en clair ; null sinon. */
    public function findActive(string $plainToken): ?AgentToken;
}
