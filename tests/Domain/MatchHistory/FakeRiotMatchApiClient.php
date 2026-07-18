<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\MatchData;
use App\Domain\MatchHistory\RiotMatchApiClientInterface;

final readonly class FakeRiotMatchApiClient implements RiotMatchApiClientInterface
{

    public function __construct(private MatchData $match)
    {
    }

    public function getMatch(string $matchId) :MatchData
    {
        return $this->match;
    }

    public function getMatchIds(string $puuid, ?int $since): array
    {
        return ['match-1'];
    }
}