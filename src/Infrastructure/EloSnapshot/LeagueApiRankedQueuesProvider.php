<?php

namespace App\Infrastructure\EloSnapshot;

use App\Domain\EloSnapshot\RankedQueuesProviderInterface;
use App\Domain\EloSnapshot\RankedQueuesSnapshot;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Infrastructure\Riot\RiotApiGateway;
use App\Infrastructure\RiotAccount\LeagueEntryMapper;

class LeagueApiRankedQueuesProvider implements RankedQueuesProviderInterface
{
    public function __construct(private readonly RiotApiGateway $gateway)
    {
    }

    public function getRankedQueues(string $puuid): RankedQueuesSnapshot
    {
        // Une entrée par file classée ; le retry rate-limit (CLI) vit dans le gateway.
        $entries = $this->gateway->getRankedsInformationsById($puuid) ?? [];

        $soloEntry = array_find(
            $entries,
            static fn($entry) => $entry->queueType === RankedQueueType::SOLO->value,
        );

        $flexEntry = array_find(
            $entries,
            static fn($entry) => $entry->queueType === RankedQueueType::FLEX->value,
        );

        return new RankedQueuesSnapshot(
            LeagueEntryMapper::map($soloEntry),
            LeagueEntryMapper::map($flexEntry),
        );
    }
}
