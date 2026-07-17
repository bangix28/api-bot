<?php

namespace App\Domain\MatchHistory;

readonly class GameHistoryEntity
{
    public function __construct(
        public bool $isWin,
        public int $championId,
        public int $kills,
        public int $deaths,
        public int $assists,
        public \DateTimeImmutable $gameEnd,
        public int $gameDuration
    )
    {
    }
}