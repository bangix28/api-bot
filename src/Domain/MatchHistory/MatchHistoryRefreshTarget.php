<?php

namespace App\Domain\MatchHistory;

/**
 * Un compte à rafraîchir : son puuid, la date (timestamp) à partir de laquelle
 * chercher ses matchs, et les files à interroger.
 */
final readonly class MatchHistoryRefreshTarget
{
    /**
     * @param MatchQueue[] $queues
     */
    public function __construct(
        public string $puuid,
        public ?int   $since,
        public array  $queues = [MatchQueue::RANKED_SOLO, MatchQueue::RANKED_FLEX],
    ) {}
}
