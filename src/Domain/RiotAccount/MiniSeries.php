<?php

namespace App\Domain\RiotAccount;

readonly class MiniSeries
{
    public function __construct(
        public int $wins,
        public int $losses,
        public int $target,
        public string $progress,
    )
    {
    }
}
