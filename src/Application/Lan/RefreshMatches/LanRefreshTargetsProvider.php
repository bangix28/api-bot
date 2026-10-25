<?php

namespace App\Application\Lan\RefreshMatches;

use App\Domain\Lan\LanEventRepositoryInterface;
use App\Domain\MatchHistory\MatchHistoryRefreshTarget;
use App\Domain\MatchHistory\MatchHistoryRefreshTargetsProviderInterface;
use App\Domain\MatchHistory\MatchQueue;
use App\Domain\Shared\ClockInterface;

/**
 * Comptes à collecter pendant une LAN : les participants des LAN en cours,
 * sur l'ARAM : Chaos uniquement, depuis le début de la LAN.
 *
 * Hors LAN, aucune cible : la commande lan:refresh peut tourner en cron toutes
 * les deux minutes sans consommer un seul appel Riot le reste du temps.
 */
final readonly class LanRefreshTargetsProvider implements MatchHistoryRefreshTargetsProviderInterface
{
    public function __construct(
        private LanEventRepositoryInterface $lanEvents,
        private ClockInterface $clock,
    ) {}

    /** @return MatchHistoryRefreshTarget[] */
    public function all(): array
    {
        // Un compte inscrit à deux LAN qui se chevauchent n'est collecté
        // qu'une fois, depuis le début de la plus ancienne.
        $since = [];

        foreach ($this->lanEvents->findInProgressAt($this->clock->now()) as $lan) {
            foreach ($lan->participantPuuids as $puuid) {
                $start = $lan->startsAt->getTimestamp();
                $since[$puuid] = min($since[$puuid] ?? $start, $start);
            }
        }

        return array_map(
            static fn(string $puuid) => new MatchHistoryRefreshTarget($puuid, $since[$puuid], [MatchQueue::ARAM_MAYHEM]),
            array_keys($since),
        );
    }
}
