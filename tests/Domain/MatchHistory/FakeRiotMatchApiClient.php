<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\MatchData;
use App\Domain\MatchHistory\RiotMatchApiClientInterface;

final class FakeRiotMatchApiClient implements RiotMatchApiClientInterface
{
    // Compteur public : permet de vérifier qu'un match déjà connu n'est pas re-téléchargé.
    public int $getMatchCallCount = 0;

    public function __construct(
        private readonly MatchData $match,
        private readonly array $matchIds = ['match-1'],
    )
    {
    }

    public function getMatch(string $matchId) :MatchData
    {
        ++$this->getMatchCallCount;

        return $this->match;
    }

    public function getMatchIds(string $puuid, ?int $since): array
    {
        return $this->matchIds;
    }
}
