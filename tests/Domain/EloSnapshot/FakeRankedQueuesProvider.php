<?php

namespace App\Tests\Domain\EloSnapshot;

use App\Domain\EloSnapshot\RankedQueuesProviderInterface;
use App\Domain\EloSnapshot\RankedQueuesSnapshot;

final class FakeRankedQueuesProvider implements RankedQueuesProviderInterface
{
    private int $callCount = 0;

    /** @param array<string, RankedQueuesSnapshot> $byPuuid */
    public function __construct(
        private readonly array $byPuuid,
        private readonly ?string $failedPuuid = null,
    ) {
    }

    public function getRankedQueues(string $puuid): RankedQueuesSnapshot
    {
        $this->callCount++;

        if ($puuid === $this->failedPuuid) {
            throw new \RuntimeException('Compte introuvable côté Riot');
        }

        return $this->byPuuid[$puuid] ?? new RankedQueuesSnapshot(null, null);
    }

    public function callCount(): int
    {
        return $this->callCount;
    }
}
