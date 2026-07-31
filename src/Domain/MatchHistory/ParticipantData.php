<?php

namespace App\Domain\MatchHistory;

readonly class ParticipantData
{
    public function __construct(
        public string $puuid,
        public bool $win,
        public int $championId,
        public string $championName,
        public string $teamPosition,
        public ScoreLine $score,
        public PlayerBuild $build,
        public CombatStats $combat,
        public MatchPerformance $performance,
    )
    {

    }
}
