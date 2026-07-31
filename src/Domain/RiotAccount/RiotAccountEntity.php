<?php

namespace App\Domain\RiotAccount;

class RiotAccountEntity
{
    public function __construct(
        private string $riotID,
        private string $puuid,
        private string $summonerName,
        private RankedQueueEntity $rankedSolo,
        private int $summonerLevel,
        private string $logoId,
        private ?RankedQueueEntity $rankedFlex = null,
    )
    {

    }


    public function withRankedSolo(RankedQueueEntity $rankedSolo): self
    {
        return new self(
            $this->riotID,
            $this->puuid,
            $this->summonerName,
            $rankedSolo,
            $this->summonerLevel,
            $this->logoId,
            $this->rankedFlex,
        );
    }

    public function withRankedFlex(?RankedQueueEntity $rankedFlex): self
    {
        return new self(
            $this->riotID,
            $this->puuid,
            $this->summonerName,
            $this->rankedSolo,
            $this->summonerLevel,
            $this->logoId,
            $rankedFlex,
        );
    }

    public function withSummonerLevel(int $summonerLevel): self
    {
        return new self(
            $this->riotID,
            $this->puuid,
            $this->summonerName,
            $this->rankedSolo,
            $summonerLevel,
            $this->logoId,
            $this->rankedFlex,
        );
    }

    public function withLogoId(string $logoId): self
    {
        return new self(
            $this->riotID,
            $this->puuid,
            $this->summonerName,
            $this->rankedSolo,
            $this->summonerLevel,
            $logoId,
            $this->rankedFlex,
        );
    }

    public function getSummonerName(): string
    {
        return $this->summonerName;
    }

    public function getPuuid(): string
    {
        return $this->puuid;
    }

    public function getRiotID(): string
    {
        return $this->riotID;
    }

    public function getRankedSolo(): RankedQueueEntity
    {
        return $this->rankedSolo;
    }

    public function getRankedFlex(): ?RankedQueueEntity
    {
        return $this->rankedFlex;
    }

    public function getSummonerLevel(): int
    {
        return $this->summonerLevel;
    }

    public function getLogoId(): string
    {
        return $this->logoId;
    }



}
