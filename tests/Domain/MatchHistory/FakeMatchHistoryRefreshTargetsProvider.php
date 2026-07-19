<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\MatchHistoryRefreshTarget;
use App\Domain\MatchHistory\MatchHistoryRefreshTargetsProviderInterface;

final class FakeMatchHistoryRefreshTargetsProvider implements MatchHistoryRefreshTargetsProviderInterface
{
    /** @param MatchHistoryRefreshTarget[] $targets */
    public function __construct(private readonly array $targets = [])
    {
    }

    /** @return MatchHistoryRefreshTarget[] */
    public function all(): array
    {
        return $this->targets;
    }
}
