<?php

namespace App\Application\MatchHistory\RefreshData;

use App\Domain\MatchHistory\MatchHistoryRefreshTargetsProviderInterface;

/**
 * Orchestrateur : rafraîchit l'historique de tous les comptes.
 * Un seul endroit qui boucle ; les points d'entrée (HTTP, CLI) délèguent ici.
 */
final readonly class RefreshAllMatchHistoryHandler
{
    public function __construct(
        private MatchHistoryRefreshTargetsProviderInterface $targetsProvider,
        private RefreshRiotMatchDataHandler                 $refreshRiotMatchDataHandler,
    ) {}

    public function handle(): void
    {
        foreach ($this->targetsProvider->all() as $target) {
            $this->refreshRiotMatchDataHandler->handle(
                new RefreshMatchHistoryCommand($target->puuid, $target->since),
            );
        }
    }
}
