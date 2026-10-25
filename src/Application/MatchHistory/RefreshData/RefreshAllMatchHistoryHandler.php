<?php

namespace App\Application\MatchHistory\RefreshData;

use App\Domain\MatchHistory\MatchHistoryRefreshTargetsProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Orchestrateur : rafraîchit l'historique des comptes que fournit le provider.
 * Un seul endroit qui boucle ; les points d'entrée (HTTP, CLI) délèguent ici.
 * Deux instances existent (cf. services.yaml) : tous les comptes sur les files
 * classées, et les participants des LAN en cours sur l'ARAM : Chaos.
 */
final readonly class RefreshAllMatchHistoryHandler
{
    public function __construct(
        private MatchHistoryRefreshTargetsProviderInterface $targetsProvider,
        private RefreshRiotMatchDataHandler                 $refreshRiotMatchDataHandler,
        private LoggerInterface                             $refreshLogger = new NullLogger(),
    ) {}

    public function handle(): void
    {
        foreach ($this->targetsProvider->all() as $target) {
            try {
                $this->refreshRiotMatchDataHandler->handle(
                    new RefreshMatchHistoryCommand($target->puuid, $target->since, $target->queues),
                );
            } catch (\Exception $e) {
                // L'échec d'un compte (erreur API, etc.) ne doit pas interrompre
                // le refresh des autres comptes.
                $this->refreshLogger->warning('Refresh de l\'historique du compte ignoré', [
                    'puuid' => $target->puuid,
                    'exception' => $e,
                ]);
            }
        }
    }
}
