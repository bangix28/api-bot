<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\RiotAccountRepository;
use App\State\PostRiotAccountProcessor;
use App\State\PutRiotAccountProcessor;
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
    processor: PostRiotAccountProcessor::class
)]

#[ApiResource(
    uriTemplate: '/user/{discordId}/riotAccount',
    operations: [ new Put(read: true) ],
    uriVariables: [
        'discordId' => new Link(
            fromProperty: 'riotAccount',
            fromClass: User::class,
        ),
    ],
    denormalizationContext: ['groups' => ['riotAccount:write:put']],
    processor: PutRiotAccountProcessor::class
)]

#[ApiResource(
    uriTemplate: '/user/{discordId}/riotAccount',
    operations: [ new Get ],
    uriVariables: [
        'discordId' => new Link(
            fromProperty: 'riotAccount',
            fromClass: User::class
        )
    ],
    normalizationContext: ['groups' => ['riotAccount:read:get']]
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
    #[Groups(['riotAccount:read:get'])]
    private ?string $riotId = '';
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['riotAccount:read:get'])]
    private ?string $puuid = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['riotAccount:write', 'riotAccount:read:get'])]
    private ?string $summonerName = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['riotAccount:read:get'])]
    private ?string $summonerRankedSoloRank = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['riotAccount:read:get'])]
    private ?string $summonerRankedSoloTier = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['riotAccount:read:get'])]
    private ?string $summonerRankedSoloLeaguePoints = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['riotAccount:read:get'])]
    private ?string $summonerRankedSoloLosses = '';

    #[ORM\Column(nullable: true)]
    #[Groups(['riotAccount:read:get'])]
    private ?int $summonerLevel = 0;

    #[ORM\Column(nullable: true)]
    #[Groups(['riotAccount:read:get'])]
    private ?int $score = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['riotAccount:read:get'])]
    private ?\DateTimeInterface $lastUpdate = null;

    #[ORM\OneToOne(inversedBy: 'riotAccount', cascade: ['persist', 'remove'])]
    private ?User $user = null;

    #[ORM\Column]
    private ?int $summonerRankedSoloWins = null;

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

    public function getSummonerRankedSoloWins(): ?int
    {
        return $this->summonerRankedSoloWins;
    }

    public function setSummonerRankedSoloWins(int $summonerRankedSoloWins): self
    {
        $this->summonerRankedSoloWins = $summonerRankedSoloWins;

        return $this;
    }

}
