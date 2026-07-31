<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\GameHistoryEntity;
use App\Domain\MatchHistory\MatchHistoryRepositoryInterface;

class InMemoryMatchHistoryRepository implements MatchHistoryRepositoryInterface
{
    private array $matches = [];

    /** @return GameHistoryEntity[] */
    public function getListMatches(): array
    {
        return array_values($this->matches);
    }

    public function save(GameHistoryEntity $gameHistoryEntity): void
    {
        // Même clé d'unicité que la vraie base : un match par compte suivi.
        $this->matches[$gameHistoryEntity->matchId . '|' . $gameHistoryEntity->puuid] = $gameHistoryEntity;
    }

    public function exists(string $matchId, string $puuid): bool
    {
        return isset($this->matches[$matchId . '|' . $puuid]);
    }
}
