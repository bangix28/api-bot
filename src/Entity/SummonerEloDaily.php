<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Repository\SummonerEloDailyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity(repositoryClass: SummonerEloDailyRepository::class)]
// Une seule ligne par compte, par jour et par file (solo/flex). Contrainte DB :
// l'idempotence applicative ne protège pas d'une course cron + déclenchement manuel.
#[ORM\UniqueConstraint(name: 'uniq_elo_daily_account_day_queue', columns: ['riot_account_id', 'date_score', 'queue_type'])]
#[ApiResource(
    operations: [
        // Courbe d'évolution quotidienne de l'elo (score = tier*1000 + division*100 + LP).
        // Pagination désactivée : 1 point/jour max (~365/an), le front filtre la période en mémoire.
        // Sans ?queueType=..., seule la solo queue est renvoyée (cf. EloDailySoloDefaultExtension).
        new GetCollection(
            uriTemplate: '/riot-account/{id}/elo-daily',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'summonerEloDailies',
                    fromClass: RiotAccount::class
                )
            ],
            normalizationContext: ['groups' => ['eloDaily:read:get']],
            order: ['dateScore' => 'ASC'],
            paginationEnabled: false
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['queueType' => 'exact'])]
class SummonerEloDaily
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['eloDaily:read:get'])]
    private ?string $score = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['eloDaily:read:get'])]
    private ?\DateTimeInterface $dateScore = null;

    #[ORM\Column(length: 20, enumType: RankedQueueType::class)]
    #[Groups(['eloDaily:read:get'])]
    private ?RankedQueueType $queueType = null;

    // Détail du rang au moment du snapshot. Nullable : les lignes historiques
    // (avant l'ajout de ces colonnes) n'ont que le score aplati — la Ranked Race
    // les ignore (tier IS NOT NULL), la courbe /elo-daily continue de lire score.
    #[ORM\Column(length: 15, nullable: true)]
    private ?string $tier = null;

    // « division » et non « rank » : RANK est un mot réservé MySQL 8.
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $division = null;

    #[ORM\Column(nullable: true)]
    private ?int $leaguePoints = null;

    #[ORM\Column(nullable: true)]
    private ?int $wins = null;

    #[ORM\Column(nullable: true)]
    private ?int $losses = null;

    #[ORM\ManyToOne(inversedBy: 'summonerEloDailies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RiotAccount $riotAccount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScore(): ?string
    {
        return $this->score;
    }

    public function setScore(string $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getDateScore(): ?\DateTimeInterface
    {
        return $this->dateScore;
    }

    public function setDateScore(\DateTimeInterface $dateScore): static
    {
        $this->dateScore = $dateScore;

        return $this;
    }

    public function getQueueType(): ?RankedQueueType
    {
        return $this->queueType;
    }

    public function setQueueType(RankedQueueType $queueType): static
    {
        $this->queueType = $queueType;

        return $this;
    }

    public function getTier(): ?string
    {
        return $this->tier;
    }

    public function setTier(?string $tier): static
    {
        $this->tier = $tier;

        return $this;
    }

    public function getDivision(): ?string
    {
        return $this->division;
    }

    public function setDivision(?string $division): static
    {
        $this->division = $division;

        return $this;
    }

    public function getLeaguePoints(): ?int
    {
        return $this->leaguePoints;
    }

    public function setLeaguePoints(?int $leaguePoints): static
    {
        $this->leaguePoints = $leaguePoints;

        return $this;
    }

    public function getWins(): ?int
    {
        return $this->wins;
    }

    public function setWins(?int $wins): static
    {
        $this->wins = $wins;

        return $this;
    }

    public function getLosses(): ?int
    {
        return $this->losses;
    }

    public function setLosses(?int $losses): static
    {
        $this->losses = $losses;

        return $this;
    }

    public function getRiotAccount(): ?RiotAccount
    {
        return $this->riotAccount;
    }

    public function setRiotAccount(?RiotAccount $riotAccount): static
    {
        $this->riotAccount = $riotAccount;

        return $this;
    }
}
