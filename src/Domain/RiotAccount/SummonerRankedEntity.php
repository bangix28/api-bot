<?php

namespace App\Domain\RiotAccount;

readonly class  SummonerRankedEntity
{
    public function __construct(
        private RankedRank $soloDivision,
        private RankedTier $soloTier,
        private int        $soloLeaguePoints,
        private int        $soloWin
    )
    {
        $this->validateSummonerRanked();
    }

    public function getSoloDivision(): RankedRank
    {
        return $this->soloDivision;
    }

    public function getSoloWin(): int
    {
        return $this->soloWin;
    }

    public function getSoloLeaguePoints(): int
    {
        return $this->soloLeaguePoints;
    }

    public function getSoloTier(): RankedTier
    {
        return $this->soloTier;
    }

    private function validateSummonerRanked(): void
    {
        if ($this->soloLeaguePoints < 0) {
            throw new \InvalidArgumentException("League points invalide : $this->soloLeaguePoints, il ne peut pas être négatif");
        }

        $isApex = in_array($this->soloTier, [
            RankedTier::MASTER,
            RankedTier::GRANDMASTER,
            RankedTier::CHALLENGER,
        ], true);

        if (!$isApex && $this->soloLeaguePoints > 99) {
            throw new \InvalidArgumentException("League points invalide : $this->soloLeaguePoints, il doit être entre 0 et 99 hors palier apex");
        }
    }

    public function getScore(): int
    {
        return $this->soloTier->getScore() + $this->soloDivision->getScore() + $this->soloLeaguePoints;
    }

}