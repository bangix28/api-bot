<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\MatchData;
use App\Domain\MatchHistory\ParticipantData;

final class MatchDataBuilder
{
    private int $gameEndTimeStamp = 1700000000000;
    private int $gameDuration = 1800;
    private array $participantData = [];


    public static function aMatch(): self { return new self(); }

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
        return new MatchData($this->gameEndTimeStamp, $this->gameDuration, $this->participantData);
    }
}