<?php

namespace App\Entity;

use App\Domain\EloSnapshot\RankedQueueType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal horodaté des rangs, alimenté toutes les 30 minutes — source de la
 * Ranked Race.
 *
 * Volontairement SANS #[ApiResource] : donnée interne, exposée uniquement à
 * travers /api/ranked-race. La courbe publique reste servie par
 * SummonerEloDaily, dont l'opération /elo-daily désactive la pagination en
 * s'appuyant sur « 1 point/jour max ».
 *
 * Toutes les colonnes sont NOT NULL : cette table naît propre, sans la dette
 * « tier IS NOT NULL » que traînent les lignes historiques du quotidien.
 */
#[ORM\Entity]
#[ORM\Table(name: 'summoner_elo_snapshot')]
// Idempotence garantie en base : l'écriture applicative ne protège pas d'un
// chevauchement de crons. Couvre aussi en préfixe la recherche du dernier point.
#[ORM\UniqueConstraint(name: 'uniq_elo_snapshot_account_queue_at', columns: ['riot_account_id', 'queue_type', 'captured_at'])]
#[ORM\Index(name: 'idx_elo_snapshot_queue_at', columns: ['queue_type', 'captured_at'])]
class SummonerEloSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Unidirectionnel : pas de collection inverse sur RiotAccount, qu'on ne veut
    // jamais hydrater avec des dizaines de milliers de points.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?RiotAccount $riotAccount = null;

    #[ORM\Column(length: 20, enumType: RankedQueueType::class)]
    private ?RankedQueueType $queueType = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $capturedAt = null;

    #[ORM\Column(length: 15)]
    private ?string $tier = null;

    // « division » et non « rank » : RANK est un mot réservé MySQL 8.
    #[ORM\Column(length: 10)]
    private ?string $division = null;

    #[ORM\Column]
    private ?int $leaguePoints = null;

    #[ORM\Column]
    private ?int $wins = null;

    #[ORM\Column]
    private ?int $losses = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRiotAccount(): ?RiotAccount
    {
        return $this->riotAccount;
    }

    public function setRiotAccount(RiotAccount $riotAccount): static
    {
        $this->riotAccount = $riotAccount;

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

    public function getCapturedAt(): ?\DateTimeImmutable
    {
        return $this->capturedAt;
    }

    public function setCapturedAt(\DateTimeImmutable $capturedAt): static
    {
        $this->capturedAt = $capturedAt;

        return $this;
    }

    public function getTier(): ?string
    {
        return $this->tier;
    }

    public function setTier(string $tier): static
    {
        $this->tier = $tier;

        return $this;
    }

    public function getDivision(): ?string
    {
        return $this->division;
    }

    public function setDivision(string $division): static
    {
        $this->division = $division;

        return $this;
    }

    public function getLeaguePoints(): ?int
    {
        return $this->leaguePoints;
    }

    public function setLeaguePoints(int $leaguePoints): static
    {
        $this->leaguePoints = $leaguePoints;

        return $this;
    }

    public function getWins(): ?int
    {
        return $this->wins;
    }

    public function setWins(int $wins): static
    {
        $this->wins = $wins;

        return $this;
    }

    public function getLosses(): ?int
    {
        return $this->losses;
    }

    public function setLosses(int $losses): static
    {
        $this->losses = $losses;

        return $this;
    }
}
