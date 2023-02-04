<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Repository\RiotAccountRepository;
use App\State\RiotAccountProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: RiotAccountRepository::class)]
#[ApiResource(
    uriTemplate: '/user/{discordId}/riotAccount',
    operations: [ new Post(read: false) ],
    uriVariables: [
        'discordId' => new Link(
            fromProperty: 'riotAccount',
            fromClass: User::class,
        ),
    ],
    denormalizationContext: ['groups' => ['riotAccount:write']],
    processor: RiotAccountProcessor::class
)]

#[ApiResource(
    uriTemplate: '/user/{discordId}/riotAccount',
    operations: [ new GetCollection() ],
    uriVariables: [
        'discordId' => new Link(
            fromProperty: 'riotAccount',
            fromClass: User::class
        )
    ]
)]

#[UniqueEntity(
    fields: 'user',
    message: 'Vous êtes déja inscrit avec cette identifiant Discord !'
)]
class RiotAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $riotId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $puuid = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('riotAccount:write')]
    private ?string $summonerName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $summonerRankedSoloRank = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $summonerRankedSoloTier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $summonerRankedSoloLeaguePoints = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $summonerRankedSoloLosses = null;

    #[ORM\Column(nullable: true)]
    private ?int $summonerLevel = null;

    #[ORM\Column(nullable: true)]
    private ?int $score = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastUpdate = null;

    #[ORM\OneToOne(inversedBy: 'riotAccount', cascade: ['persist', 'remove'])]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRiotId(): ?string
    {
        return $this->riotId;
    }

    public function setRiotId(?string $riotId): self
    {
        $this->riotId = $riotId;

        return $this;
    }

    public function getPuuid(): ?string
    {
        return $this->puuid;
    }

    public function setPuuid(?string $puuid): self
    {
        $this->puuid = $puuid;

        return $this;
    }

    public function getSummonerName(): ?string
    {
        return $this->summonerName;
    }

    public function setSummonerName(?string $summonerName): self
    {
        $this->summonerName = $summonerName;

        return $this;
    }

    public function getSummonerRankedSoloRank(): ?string
    {
        return $this->summonerRankedSoloRank;
    }

    public function setSummonerRankedSoloRank(?string $summonerRankedSoloRank): self
    {
        $this->summonerRankedSoloRank = $summonerRankedSoloRank;

        return $this;
    }

    public function getSummonerRankedSoloTier(): ?string
    {
        return $this->summonerRankedSoloTier;
    }

    public function setSummonerRankedSoloTier(?string $summonerRankedSoloTier): self
    {
        $this->summonerRankedSoloTier = $summonerRankedSoloTier;

        return $this;
    }

    public function getSummonerRankedSoloLeaguePoints(): ?string
    {
        return $this->summonerRankedSoloLeaguePoints;
    }

    public function setSummonerRankedSoloLeaguePoints(?string $summonerRankedSoloLeaguePoints): self
    {
        $this->summonerRankedSoloLeaguePoints = $summonerRankedSoloLeaguePoints;

        return $this;
    }

    public function getSummonerRankedSoloLosses(): ?string
    {
        return $this->summonerRankedSoloLosses;
    }

    public function setSummonerRankedSoloLosses(?string $summonerRankedSoloLosses): self
    {
        $this->summonerRankedSoloLosses = $summonerRankedSoloLosses;

        return $this;
    }

    public function getSummonerLevel(): ?int
    {
        return $this->summonerLevel;
    }

    public function setSummonerLevel(?int $summonerLevel): self
    {
        $this->summonerLevel = $summonerLevel;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getLastUpdate(): ?\DateTimeInterface
    {
        return $this->lastUpdate;
    }

    public function setLastUpdate(?\DateTimeInterface $lastUpdate): self
    {
        $this->lastUpdate = $lastUpdate;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
}
