<?php

namespace App\Domain\MatchHistory;

/**
 * Un compte à rafraîchir : son puuid et la date (timestamp) du dernier refresh.
 */
final readonly class MatchHistoryRefreshTarget
{
    public function __construct(
        public string $puuid,
        public ?int   $since,
    ) {}
}
