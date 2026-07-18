<?php

namespace App\Domain\MatchHistory;

interface RiotMatchApiClientInterface
{
    /** @return array<string> */
    public function getMatchIds(string $puuid, ?int $since): array;

    public function getMatch(string $matchId): MatchData;
}