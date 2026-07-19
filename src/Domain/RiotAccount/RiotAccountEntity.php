<?php

namespace App\Domain\RiotAccount;

class RiotAccountEntity
{
    public function __construct(
        private string $riotID,
        private string $puuid,
        private string $summonerName,
        private SummonerRankedEntity $ranked,
        private int $summonerLevel,
        private string $logoId
    )
    {

    }


    public function withRanked(SummonerRankedEntity $ranked): self
    {
        return new self(
            $this->riotID,
            $this->puuid,
            $this->summonerName,
            $ranked,
            $this->summonerLevel,
            $this->logoId
        );
    }

    public function withSummonerLevel(int $summonerLevel): self
    {
        return new self(
            $this->riotID,
            $this->puuid,
            $this->summonerName,
            $this->ranked,
            $summonerLevel,
            $this->logoId
        );
    }

    public function withLogoId(string $logoId): self
    {
        return new self(
            $this->riotID,
            $this->puuid,
            $this->summonerName,
            $this->ranked,
            $this->summonerLevel,
            $logoId
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

    public function getRanked(): SummonerRankedEntity
    {
        return $this->ranked;
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