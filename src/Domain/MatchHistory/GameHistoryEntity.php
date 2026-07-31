<?php

namespace App\Domain\MatchHistory;

readonly class GameHistoryEntity
{
    public function __construct(
        public string $matchId,
        public int $queueId,
        public bool $isWin,
        public int $championId,
        public string $championName,
        public string $teamPosition,
        public ScoreLine $score,
        public PlayerBuild $build,
        public CombatStats $combat,
        public MatchPerformance $performance,
        public \DateTimeImmutable $gameEnd,
        public int $gameDuration,
        public string $puuid,
    )
    {
    }
}
