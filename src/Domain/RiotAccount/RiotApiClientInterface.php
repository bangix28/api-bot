<?php

namespace App\Domain\RiotAccount;

interface RiotApiClientInterface
{
    public function getAccount(string $puuid): RiotAccountRefreshData;
}