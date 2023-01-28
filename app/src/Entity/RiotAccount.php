<?php

namespace App\Entity;

use App\Repository\RiotAccountRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RiotAccountRepository::class)]
class RiotAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $riotId = null;

    #[ORM\Column(length: 255)]
    private ?string $riotPuuid = null;

    #[ORM\Column(length: 255)]
    private ?string $summonerName = null;

    #[ORM\Column(length: 255)]
    private ?string $summonerRankedSoloRank = null;

    #[ORM\Column(length: 255)]
    private ?string $summonerRankedSoloTier = null;

    #[ORM\Column]
    private ?int $summonerRankedSoloLeaguePoints = null;

    #[ORM\Column(length: 255)]
    private ?string $summonerRankedSoloWins = null;

    #[ORM\Column(length: 255)]
    private ?string $SummonerRankedSoloLosses = null;

    #[ORM\Column(length: 255)]
    private ?string $SummonerLevel = null;

    #[ORM\Column(length: 255)]
    private ?string $lastUpdate = null;

    #[ORM\OneToOne(inversedBy: 'riotAccount', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?user $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRiotId(): ?string
    {
        return $this->riotId;
    }

    public function setRiotId(string $riotId): self
    {
        $this->riotId = $riotId;

        return $this;
    }

    public function getRiotPuuid(): ?string
    {
        return $this->riotPuuid;
    }

    public function setRiotPuuid(string $riotPuuid): self
    {
        $this->riotPuuid = $riotPuuid;

        return $this;
    }

    public function getSummonerName(): ?string
    {
        return $this->summonerName;
    }

    public function setSummonerName(string $summonerName): self
    {
        $this->summonerName = $summonerName;

        return $this;
    }

    public function getSummonerRankedSoloRank(): ?string
    {
        return $this->summonerRankedSoloRank;
    }

    public function setSummonerRankedSoloRank(string $summonerRankedSoloRank): self
    {
        $this->summonerRankedSoloRank = $summonerRankedSoloRank;

        return $this;
    }

    public function getSummonerRankedSoloTier(): ?string
    {
        return $this->summonerRankedSoloTier;
    }

    public function setSummonerRankedSoloTier(string $summonerRankedSoloTier): self
    {
        $this->summonerRankedSoloTier = $summonerRankedSoloTier;

        return $this;
    }

    public function getSummonerRankedSoloLeaguePoints(): ?int
    {
        return $this->summonerRankedSoloLeaguePoints;
    }

    public function setSummonerRankedSoloLeaguePoints(int $summonerRankedSoloLeaguePoints): self
    {
        $this->summonerRankedSoloLeaguePoints = $summonerRankedSoloLeaguePoints;

        return $this;
    }

    public function getSummonerRankedSoloWins(): ?string
    {
        return $this->summonerRankedSoloWins;
    }

    public function setSummonerRankedSoloWins(string $summonerRankedSoloWins): self
    {
        $this->summonerRankedSoloWins = $summonerRankedSoloWins;

        return $this;
    }

    public function getSummonerRankedSoloLosses(): ?string
    {
        return $this->SummonerRankedSoloLosses;
    }

    public function setSummonerRankedSoloLosses(string $SummonerRankedSoloLosses): self
    {
        $this->SummonerRankedSoloLosses = $SummonerRankedSoloLosses;

        return $this;
    }

    public function getSummonerLevel(): ?string
    {
        return $this->SummonerLevel;
    }

    public function setSummonerLevel(string $SummonerLevel): self
    {
        $this->SummonerLevel = $SummonerLevel;

        return $this;
    }

    public function getLastUpdate(): ?string
    {
        return $this->lastUpdate;
    }

    public function setLastUpdate(string $lastUpdate): self
    {
        $this->lastUpdate = $lastUpdate;

        return $this;
    }

    public function getUser(): ?user
    {
        return $this->user;
    }

    public function setUser(user $user): self
    {
        $this->user = $user;

        return $this;
    }
}
