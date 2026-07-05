<?php

namespace App\Tests\Domain\RiotAccount;

use App\Domain\RiotAccount\RiotAccountRefreshData;
use App\Domain\RiotAccount\RiotApiClientInterface;

class FakeRiotApiClient implements RiotApiClientInterface
{
    public function __construct(private readonly RiotAccountRefreshData $refreshData)
    {

    }
    public function getAccount(string $puuid): RiotAccountRefreshData
    {
        return $this->refreshData;
    }
}