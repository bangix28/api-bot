<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\MatchData;
use App\Domain\MatchHistory\ParticipantData;

final class MatchDataBuilder
{
    private string $matchId = 'EUW1_1';
    private int $queueId = 420;
    private int $gameEndTimeStamp = 1700000000000;
    private int $gameDuration = 1800;
    private array $participantData = [];


    public static function aMatch(): self { return new self(); }

    public function withMatchId(string $matchId): MatchDataBuilder
    {
        $this->matchId = $matchId;
        return $this;
    }

    public function withQueueId(int $queueId): MatchDataBuilder
    {
        $this->queueId = $queueId;
        return $this;
    }

    public function withParticipantData(ParticipantData $participantData): MatchDataBuilder
    {
        $this->participantData[] = $participantData;
        return $this;
    }

    public function withGameEndTimeStamp(int $gameEndTimeStamp): MatchDataBuilder
    {
        $this->gameEndTimeStamp = $gameEndTimeStamp;
        return $this;
    }

    public function withGameDuration(int $gameDuration): MatchDataBuilder
    {
        $this->gameDuration = $gameDuration;
        return $this;
    }

    public function build(): MatchData
    {
        return new MatchData($this->matchId, $this->queueId, $this->gameEndTimeStamp, $this->gameDuration, $this->participantData);
    }
}
