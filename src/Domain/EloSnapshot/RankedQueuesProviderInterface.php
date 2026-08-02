<?php

namespace App\Domain\EloSnapshot;

/**
 * Port Riot dédié au snapshot : un seul appel League-V4 par compte
 * (contrairement à RiotApiClientInterface::getAccount() qui en fait deux,
 * dont un summoner-by-puuid inutile ici).
 */
interface RankedQueuesProviderInterface
{
    public function getRankedQueues(string $puuid): RankedQueuesSnapshot;
}
