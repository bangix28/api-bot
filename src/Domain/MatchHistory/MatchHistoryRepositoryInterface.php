<?php

namespace App\Domain\MatchHistory;

interface MatchHistoryRepositoryInterface
{
    public function save(GameHistoryEntity $gameHistoryEntity): void;
}