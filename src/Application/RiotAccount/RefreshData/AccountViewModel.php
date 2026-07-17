<?php

namespace App\Application\RiotAccount\RefreshData;

final readonly class AccountViewModel
{
    public function __construct(
        public string $summonerName,
        public string $tier,
        public string $rank,
        public int    $leaguePoints,
    ) {}
}
