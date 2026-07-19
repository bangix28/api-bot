<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\ParticipantData;

final class ParticipantDataBuilder
{
    private string $puuid = 'puuid-1';
    private bool $win = true;
    private int $championId = 64;
    private int $kills = 10;
    private int $deaths = 2;
    private int $assists = 8;

    public static function aParticipant(): self { return new self(); }

    public function withPuuid(string $puuid): ParticipantDataBuilder
    {
        $this->puuid = $puuid;
        return $this;
    }

    public function withWin(bool $win): ParticipantDataBuilder
    {
        $this->win = $win;
        return $this;
    }

    public function withChampionId(int $championId): ParticipantDataBuilder
    {
        $this->championId = $championId;
        return $this;
    }

    public function withKills(int $kills): ParticipantDataBuilder
    {
        $this->kills = $kills;
        return $this;
    }

    public function withDeaths(int $deaths): ParticipantDataBuilder
    {
        $this->deaths = $deaths;
        return $this;
    }

    public function withAssists(int $assists): ParticipantDataBuilder
    {
        $this->assists = $assists;
        return $this;
    }

    public function build(): ParticipantData {
        return new ParticipantData($this->puuid, $this->win, $this->championId, $this->kills, $this->deaths, $this->assists);
    }
}