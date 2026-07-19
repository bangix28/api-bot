<?php

namespace App\Application\MatchHistory\RefreshData;

use App\Domain\MatchHistory\MatchHistoryRefreshTargetsProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Orchestrateur : rafraîchit l'historique de tous les comptes.
 * Un seul endroit qui boucle ; les points d'entrée (HTTP, CLI) délèguent ici.
 */
final readonly class RefreshAllMatchHistoryHandler
{
    public function __construct(
        private MatchHistoryRefreshTargetsProviderInterface $targetsProvider,
        private RefreshRiotMatchDataHandler                 $refreshRiotMatchDataHandler,
        private LoggerInterface                             $logger = new NullLogger(),
    ) {}

    public function handle(): void
    {
        foreach ($this->targetsProvider->all() as $target) {
            try {
                $this->refreshRiotMatchDataHandler->handle(
                    new RefreshMatchHistoryCommand($target->puuid, $target->since),
                );
            } catch (\Exception $e) {
                // L'échec d'un compte (erreur API, etc.) ne doit pas interrompre
                // le refresh des autres comptes.
                $this->logger->warning('Refresh de l\'historique du compte ignoré', [
                    'puuid' => $target->puuid,
                    'exception' => $e,
                ]);
            }
        }
    }
}
