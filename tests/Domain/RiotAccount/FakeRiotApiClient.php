<?php

namespace App\Tests\Domain\RiotAccount;

use App\Domain\RiotAccount\RiotAccountRefreshData;
use App\Domain\RiotAccount\RiotApiClientInterface;

final class FakeRiotApiClient implements RiotApiClientInterface
{
    /** Compte les appels : sert à prouver qu'une évolution n'en ajoute aucun. */
    private int $calls = 0;

    public function __construct(
        private readonly RiotAccountRefreshData $refreshData,
        private readonly ?string $failedPuuid = null,
    ) {
    }

    public function getAccount(string $puuid): RiotAccountRefreshData
    {
        $this->calls++;

        if ($puuid === $this->failedPuuid) {
            throw new \RuntimeException('Compte introuvable côté Riot');
        }

        return $this->refreshData;
    }

    public function callCount(): int
    {
        return $this->calls;
    }
}
