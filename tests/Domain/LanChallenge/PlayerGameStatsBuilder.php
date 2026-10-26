<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\PlayerGameStats;

/**
 * Relevé neutre par défaut : aucune carte n'est réussie sans réglage explicite.
 * Résultat et premier sang inconnus : une victoire comme une défaite ferait
 * réussir une carte (Mauvais perdant gagne sur une défaite).
 */
final class PlayerGameStatsBuilder
{
    private string $puuid = 'puuid-1';
    private int $kills = 0;
    private int $deaths = 6;
    private int $assists = 0;
    private int $largestMultiKill = 0;
    private int $soloKills = 0;
    private int $teamKills = 20;
    private int $aces = 0;
    private ?bool $win = null;
    private ?bool $firstBlood = null;
    private ?bool $mostDeathsInGame = false;

    public static function aPlayer(string $puuid = 'puuid-1'): self
    {
        $builder = new self();
        $builder->puuid = $puuid;

        return $builder;
    }

    public function withKills(int $kills): self { $this->kills = $kills; return $this; }
    public function withDeaths(int $deaths): self { $this->deaths = $deaths; return $this; }
    public function withAssists(int $assists): self { $this->assists = $assists; return $this; }
    public function withLargestMultiKill(int $streak): self { $this->largestMultiKill = $streak; return $this; }
    public function withSoloKills(int $soloKills): self { $this->soloKills = $soloKills; return $this; }
    public function withTeamKills(int $teamKills): self { $this->teamKills = $teamKills; return $this; }
    public function withAces(int $aces): self { $this->aces = $aces; return $this; }
    public function withWin(?bool $win): self { $this->win = $win; return $this; }
    public function withFirstBlood(?bool $firstBlood): self { $this->firstBlood = $firstBlood; return $this; }
    public function withMostDeathsInGame(?bool $mostDeathsInGame): self { $this->mostDeathsInGame = $mostDeathsInGame; return $this; }

    public function build(): PlayerGameStats
    {
        return new PlayerGameStats(
            $this->puuid, $this->kills, $this->deaths, $this->assists, $this->largestMultiKill,
            $this->soloKills, $this->teamKills, $this->aces, $this->win, $this->firstBlood, $this->mostDeathsInGame,
        );
    }
}
