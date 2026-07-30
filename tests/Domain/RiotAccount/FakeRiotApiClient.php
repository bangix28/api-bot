<?php

namespace App\Tests\Domain\RiotAccount;

use App\Domain\RiotAccount\RiotAccountRefreshData;
use App\Domain\RiotAccount\RiotApiClientInterface;

final readonly class FakeRiotApiClient implements RiotApiClientInterface
{
    public function __construct(
        private RiotAccountRefreshData $refreshData,
        private ?string $failedPuuid = null,
    ) {
    }

    public function getAccount(string $puuid): RiotAccountRefreshData
    {
        if ($puuid === $this->failedPuuid) {
            throw new \RuntimeException('Compte introuvable côté Riot');
        }

        return $this->refreshData;
    }
}