<?php

namespace App\Application\MatchHistory\RefreshData;

use App\Domain\MatchHistory\MatchQueue;

final readonly class RefreshMatchHistoryCommand
{
    /**
     * @param MatchQueue[] $queues files à interroger ; par défaut les deux files classées
     */
    public function __construct(
        public string $puuid,
        public ?int $since,
        public array $queues = [MatchQueue::RANKED_SOLO, MatchQueue::RANKED_FLEX],
    )
    {
    }
}
