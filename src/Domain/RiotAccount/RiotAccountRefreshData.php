<?php

namespace App\Domain\RiotAccount;

readonly class RiotAccountRefreshData
{
    public function __construct(
        public SummonerRankedEntity $ranked,
        public int                  $summonerLevel,
        public string               $logoId,
    ) {}
}