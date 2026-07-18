<?php

namespace App\Application\MatchHistory\RefreshData;

final readonly class RefreshMatchHistoryCommand
{
    public function __construct(
        public string $puuid,
        public ?int $since,
    )
    {
    }
}