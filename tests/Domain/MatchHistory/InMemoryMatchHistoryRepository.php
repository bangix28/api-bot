<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\GameHistoryEntity;
use App\Domain\MatchHistory\MatchHistoryRepositoryInterface;
use App\Domain\RiotAccount\RiotAccountEntity;

class InMemoryMatchHistoryRepository implements MatchHistoryRepositoryInterface
{
    private array $matches = [];

    /** @return RiotAccountEntity[] */
    public function getListMatches(): array
    {
        return array_values($this->matches);
    }

    public function save(GameHistoryEntity $gameHistoryEntity): void
    {
        $this->matches[$gameHistoryEntity->puuid] = $gameHistoryEntity;
    }
}