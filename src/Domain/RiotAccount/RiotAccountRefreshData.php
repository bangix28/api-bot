<?php

namespace App\Domain\RiotAccount;

readonly class RiotAccountRefreshData
{
    public function __construct(
        public RankedQueueEntity  $rankedSolo,
        public ?RankedQueueEntity $rankedFlex,
        public int                $summonerLevel,
        public string             $logoId,
    ) {}
}
