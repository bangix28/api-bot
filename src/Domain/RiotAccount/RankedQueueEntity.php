<?php

namespace App\Domain\RiotAccount;

readonly class RankedQueueEntity
{
    public function __construct(
        private RankedRank  $division,
        private RankedTier  $tier,
        private int         $leaguePoints,
        private int         $wins,
        private int         $losses,
        private bool        $hotStreak = false,
        private bool        $veteran = false,
        private bool        $freshBlood = false,
        private ?MiniSeries $miniSeries = null,
    )
    {
        $this->validateRankedQueue();
    }

    public function getDivision(): RankedRank
    {
        return $this->division;
    }

    public function getWins(): int
    {
        return $this->wins;
    }

    public function getLosses(): int
    {
        return $this->losses;
    }

    public function getLeaguePoints(): int
    {
        return $this->leaguePoints;
    }

    public function getTier(): RankedTier
    {
        return $this->tier;
    }

    public function isHotStreak(): bool
    {
        return $this->hotStreak;
    }

    public function isVeteran(): bool
    {
        return $this->veteran;
    }

    public function isFreshBlood(): bool
    {
        return $this->freshBlood;
    }

    public function getMiniSeries(): ?MiniSeries
    {
        return $this->miniSeries;
    }

    private function validateRankedQueue(): void
    {
        if ($this->leaguePoints < 0) {
            throw new \InvalidArgumentException("League points invalide : $this->leaguePoints, il ne peut pas être négatif");
        }

        if ($this->losses < 0) {
            throw new \InvalidArgumentException("Nombre de défaites invalide : $this->losses, il ne peut pas être négatif");
        }

        $isApex = in_array($this->tier, [
            RankedTier::MASTER,
            RankedTier::GRANDMASTER,
            RankedTier::CHALLENGER,
        ], true);

        if (!$isApex && $this->leaguePoints > 99) {
            throw new \InvalidArgumentException("League points invalide : $this->leaguePoints, il doit être entre 0 et 99 hors palier apex");
        }
    }

    public function getScore(): int
    {
        return $this->tier->getScore() + $this->division->getScore() + $this->leaguePoints;
    }

}
