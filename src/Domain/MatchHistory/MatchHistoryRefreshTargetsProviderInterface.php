<?php

namespace App\Domain\MatchHistory;

interface MatchHistoryRefreshTargetsProviderInterface
{
    /** @return MatchHistoryRefreshTarget[] */
    public function all(): array;
}
