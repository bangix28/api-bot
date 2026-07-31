<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\CombatStats;
use App\Domain\MatchHistory\MatchPerformance;
use App\Domain\MatchHistory\ParticipantData;
use App\Domain\MatchHistory\PlayerBuild;
use App\Domain\MatchHistory\Runes;
use App\Domain\MatchHistory\ScoreLine;

final class ParticipantDataBuilder
{
    private string $puuid = 'puuid-1';
    private bool $win = true;
    private int $championId = 64;
    private string $championName = 'LeeSin';
    private string $teamPosition = 'JUNGLE';
    private int $kills = 10;
    private int $deaths = 2;
    private int $assists = 8;
    private int $champLevel = 15;
    private int $goldEarned = 12000;
    private int $creepScore = 180;
    private int $visionScore = 25;
    private PlayerBuild $build;
    private CombatStats $combat;
    private MatchPerformance $performance;

    private function __construct()
    {
        $this->build = new PlayerBuild(
            [3078, 3111, 3071, 3053, 3065, 0, 3364],
            4,
            11,
            new Runes(8010, 8000, 8100, 5001, 5008, 5005),
        );
        $this->combat = new CombatStats(18000, 22000, 1, 0, 0, 0, false, false);
        $this->performance = new MatchPerformance(9.0, 0.55, 600.0, 400.0, 0.8);
    }

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

    public function withChampionName(string $championName): ParticipantDataBuilder
    {
        $this->championName = $championName;
        return $this;
    }

    public function withTeamPosition(string $teamPosition): ParticipantDataBuilder
    {
        $this->teamPosition = $teamPosition;
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

    public function withBuild(PlayerBuild $build): ParticipantDataBuilder
    {
        $this->build = $build;
        return $this;
    }

    public function withCombat(CombatStats $combat): ParticipantDataBuilder
    {
        $this->combat = $combat;
        return $this;
    }

    public function withPerformance(MatchPerformance $performance): ParticipantDataBuilder
    {
        $this->performance = $performance;
        return $this;
    }

    public function build(): ParticipantData {
        return new ParticipantData(
            $this->puuid,
            $this->win,
            $this->championId,
            $this->championName,
            $this->teamPosition,
            new ScoreLine(
                $this->kills,
                $this->deaths,
                $this->assists,
                $this->champLevel,
                $this->goldEarned,
                $this->creepScore,
                $this->visionScore,
            ),
            $this->build,
            $this->combat,
            $this->performance,
        );
    }
}
