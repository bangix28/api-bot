<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\HistoryAccountLolRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: HistoryAccountLolRepository::class)]
// Idempotence : un même match ne peut être stocké qu'une fois par compte suivi
// (composite car deux comptes suivis peuvent partager le même match).
#[ORM\UniqueConstraint(name: 'uniq_match_per_account', columns: ['match_id', 'riot_account_id'])]
#[ApiResource(
    operations: [
        // Historique des 5 dernières games (socle + stats per-minute, sans le build)
        new GetCollection(
            uriTemplate: '/riot-account/{id}/history-account-lol',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'historyAccountLols',
                    fromClass: RiotAccount::class
                )
            ],
            normalizationContext: ['groups' => ['historyAccount:read:get']],
            order: ['dateGameEnd' => 'DESC'],
            paginationItemsPerPage: 5
        ),
        // Détails complets d'une game
        new Get(
            uriTemplate: '/history-account-lol/{id}',
            normalizationContext: ['groups' => ['historyAccount:read:get', 'historyAccount:read:detail']]
        ),
    ]
)]
class HistoryAccountLol
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['historyAccount:read:get'])]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\ManyToOne(inversedBy: 'historyAccountLols')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RiotAccount $riotAccount = null;

    // Nullable : les lignes créées avant l'ajout de cette colonne n'ont pas de matchId.
    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?string $matchId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?int $queueId = null;

    #[ORM\Column]
    #[Groups(['historyAccount:read:get'])]
    private ?bool $isWin = null;

    #[ORM\Column]
    #[Groups(['historyAccount:read:get'])]
    private ?int $championId = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?string $championName = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?string $teamPosition = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['historyAccount:read:get'])]
    private ?\DateTimeImmutable $dateGameEnd = null;

    #[ORM\Column]
    #[Groups(['historyAccount:read:get'])]
    private ?int $killPlayer = null;

    #[ORM\Column]
    #[Groups(['historyAccount:read:get'])]
    private ?int $deathPlayer = null;

    #[ORM\Column]
    #[Groups(['historyAccount:read:get'])]
    private ?int $assistPlayer = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?int $champLevel = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?int $creepScore = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?int $visionScore = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $goldEarned = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $item0 = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $item1 = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $item2 = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $item3 = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $item4 = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $item5 = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $item6 = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $summonerSpell1Id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $summonerSpell2Id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $runeKeystoneId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $runePrimaryStyleId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $runeSubStyleId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $runeStatDefense = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $runeStatFlex = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $runeStatOffense = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $totalDamageDealtToChampions = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $totalDamageTaken = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $doubleKills = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $tripleKills = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $quadraKills = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?int $pentaKills = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?bool $firstBloodKill = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:detail'])]
    private ?bool $gameEndedInSurrender = null;

    // Challenges Riot : null = donnée absente (vieux match, mode spécial).
    // Exposés aussi dans la collection (groupe :get) : le front calcule les
    // moyennes du joueur (radar "vs moyennes") à partir de la liste des matchs.
    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?float $kda = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?float $killParticipation = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?float $damagePerMinute = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?float $goldPerMinute = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?float $visionScorePerMinute = null;

    // Durée en minutes.
    #[ORM\Column(nullable: true)]
    #[Groups(['historyAccount:read:get'])]
    private ?int $gameDuration = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

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

    public function getMatchId(): ?string
    {
        return $this->matchId;
    }

    public function setMatchId(?string $matchId): static
    {
        $this->matchId = $matchId;

        return $this;
    }

    public function getQueueId(): ?int
    {
        return $this->queueId;
    }

    public function setQueueId(?int $queueId): static
    {
        $this->queueId = $queueId;

        return $this;
    }

    public function getIsWin(): bool
    {
        return $this->isWin;
    }

    public function setIsWin(bool $isWin): static
    {
        $this->isWin = $isWin;

        return $this;
    }

    public function getChampionId(): ?int
    {
        return $this->championId;
    }

    public function setChampionId(int $championId): static
    {
        $this->championId = $championId;

        return $this;
    }

    public function getChampionName(): ?string
    {
        return $this->championName;
    }

    public function setChampionName(?string $championName): static
    {
        $this->championName = $championName;

        return $this;
    }

    public function getTeamPosition(): ?string
    {
        return $this->teamPosition;
    }

    public function setTeamPosition(?string $teamPosition): static
    {
        $this->teamPosition = $teamPosition;

        return $this;
    }

    public function getDateGameEnd(): ?\DateTimeImmutable
    {
        return $this->dateGameEnd;
    }

    public function setDateGameEnd(\DateTimeImmutable $dateGameEnd): static
    {
        $this->dateGameEnd = $dateGameEnd;

        return $this;
    }

    public function getKillPlayer(): ?int
    {
        return $this->killPlayer;
    }

    public function setKillPlayer(int $killPlayer): static
    {
        $this->killPlayer = $killPlayer;

        return $this;
    }

    public function getDeathPlayer(): ?int
    {
        return $this->deathPlayer;
    }

    public function setDeathPlayer(int $deathPlayer): static
    {
        $this->deathPlayer = $deathPlayer;

        return $this;
    }

    public function getAssistPlayer(): ?int
    {
        return $this->assistPlayer;
    }

    public function setAssistPlayer(int $assistPlayer): static
    {
        $this->assistPlayer = $assistPlayer;

        return $this;
    }

    public function getChampLevel(): ?int
    {
        return $this->champLevel;
    }

    public function setChampLevel(?int $champLevel): static
    {
        $this->champLevel = $champLevel;

        return $this;
    }

    public function getCreepScore(): ?int
    {
        return $this->creepScore;
    }

    public function setCreepScore(?int $creepScore): static
    {
        $this->creepScore = $creepScore;

        return $this;
    }

    public function getVisionScore(): ?int
    {
        return $this->visionScore;
    }

    public function setVisionScore(?int $visionScore): static
    {
        $this->visionScore = $visionScore;

        return $this;
    }

    public function getGoldEarned(): ?int
    {
        return $this->goldEarned;
    }

    public function setGoldEarned(?int $goldEarned): static
    {
        $this->goldEarned = $goldEarned;

        return $this;
    }

    public function getItem0(): ?int
    {
        return $this->item0;
    }

    public function setItem0(?int $item0): static
    {
        $this->item0 = $item0;

        return $this;
    }

    public function getItem1(): ?int
    {
        return $this->item1;
    }

    public function setItem1(?int $item1): static
    {
        $this->item1 = $item1;

        return $this;
    }

    public function getItem2(): ?int
    {
        return $this->item2;
    }

    public function setItem2(?int $item2): static
    {
        $this->item2 = $item2;

        return $this;
    }

    public function getItem3(): ?int
    {
        return $this->item3;
    }

    public function setItem3(?int $item3): static
    {
        $this->item3 = $item3;

        return $this;
    }

    public function getItem4(): ?int
    {
        return $this->item4;
    }

    public function setItem4(?int $item4): static
    {
        $this->item4 = $item4;

        return $this;
    }

    public function getItem5(): ?int
    {
        return $this->item5;
    }

    public function setItem5(?int $item5): static
    {
        $this->item5 = $item5;

        return $this;
    }

    public function getItem6(): ?int
    {
        return $this->item6;
    }

    public function setItem6(?int $item6): static
    {
        $this->item6 = $item6;

        return $this;
    }

    public function getSummonerSpell1Id(): ?int
    {
        return $this->summonerSpell1Id;
    }

    public function setSummonerSpell1Id(?int $summonerSpell1Id): static
    {
        $this->summonerSpell1Id = $summonerSpell1Id;

        return $this;
    }

    public function getSummonerSpell2Id(): ?int
    {
        return $this->summonerSpell2Id;
    }

    public function setSummonerSpell2Id(?int $summonerSpell2Id): static
    {
        $this->summonerSpell2Id = $summonerSpell2Id;

        return $this;
    }

    public function getRuneKeystoneId(): ?int
    {
        return $this->runeKeystoneId;
    }

    public function setRuneKeystoneId(?int $runeKeystoneId): static
    {
        $this->runeKeystoneId = $runeKeystoneId;

        return $this;
    }

    public function getRunePrimaryStyleId(): ?int
    {
        return $this->runePrimaryStyleId;
    }

    public function setRunePrimaryStyleId(?int $runePrimaryStyleId): static
    {
        $this->runePrimaryStyleId = $runePrimaryStyleId;

        return $this;
    }

    public function getRuneSubStyleId(): ?int
    {
        return $this->runeSubStyleId;
    }

    public function setRuneSubStyleId(?int $runeSubStyleId): static
    {
        $this->runeSubStyleId = $runeSubStyleId;

        return $this;
    }

    public function getRuneStatDefense(): ?int
    {
        return $this->runeStatDefense;
    }

    public function setRuneStatDefense(?int $runeStatDefense): static
    {
        $this->runeStatDefense = $runeStatDefense;

        return $this;
    }

    public function getRuneStatFlex(): ?int
    {
        return $this->runeStatFlex;
    }

    public function setRuneStatFlex(?int $runeStatFlex): static
    {
        $this->runeStatFlex = $runeStatFlex;

        return $this;
    }

    public function getRuneStatOffense(): ?int
    {
        return $this->runeStatOffense;
    }

    public function setRuneStatOffense(?int $runeStatOffense): static
    {
        $this->runeStatOffense = $runeStatOffense;

        return $this;
    }

    public function getTotalDamageDealtToChampions(): ?int
    {
        return $this->totalDamageDealtToChampions;
    }

    public function setTotalDamageDealtToChampions(?int $totalDamageDealtToChampions): static
    {
        $this->totalDamageDealtToChampions = $totalDamageDealtToChampions;

        return $this;
    }

    public function getTotalDamageTaken(): ?int
    {
        return $this->totalDamageTaken;
    }

    public function setTotalDamageTaken(?int $totalDamageTaken): static
    {
        $this->totalDamageTaken = $totalDamageTaken;

        return $this;
    }

    public function getDoubleKills(): ?int
    {
        return $this->doubleKills;
    }

    public function setDoubleKills(?int $doubleKills): static
    {
        $this->doubleKills = $doubleKills;

        return $this;
    }

    public function getTripleKills(): ?int
    {
        return $this->tripleKills;
    }

    public function setTripleKills(?int $tripleKills): static
    {
        $this->tripleKills = $tripleKills;

        return $this;
    }

    public function getQuadraKills(): ?int
    {
        return $this->quadraKills;
    }

    public function setQuadraKills(?int $quadraKills): static
    {
        $this->quadraKills = $quadraKills;

        return $this;
    }

    public function getPentaKills(): ?int
    {
        return $this->pentaKills;
    }

    public function setPentaKills(?int $pentaKills): static
    {
        $this->pentaKills = $pentaKills;

        return $this;
    }

    public function getFirstBloodKill(): ?bool
    {
        return $this->firstBloodKill;
    }

    public function setFirstBloodKill(?bool $firstBloodKill): static
    {
        $this->firstBloodKill = $firstBloodKill;

        return $this;
    }

    public function getGameEndedInSurrender(): ?bool
    {
        return $this->gameEndedInSurrender;
    }

    public function setGameEndedInSurrender(?bool $gameEndedInSurrender): static
    {
        $this->gameEndedInSurrender = $gameEndedInSurrender;

        return $this;
    }

    public function getKda(): ?float
    {
        return $this->kda;
    }

    public function setKda(?float $kda): static
    {
        $this->kda = $kda;

        return $this;
    }

    public function getKillParticipation(): ?float
    {
        return $this->killParticipation;
    }

    public function setKillParticipation(?float $killParticipation): static
    {
        $this->killParticipation = $killParticipation;

        return $this;
    }

    public function getDamagePerMinute(): ?float
    {
        return $this->damagePerMinute;
    }

    public function setDamagePerMinute(?float $damagePerMinute): static
    {
        $this->damagePerMinute = $damagePerMinute;

        return $this;
    }

    public function getGoldPerMinute(): ?float
    {
        return $this->goldPerMinute;
    }

    public function setGoldPerMinute(?float $goldPerMinute): static
    {
        $this->goldPerMinute = $goldPerMinute;

        return $this;
    }

    public function getVisionScorePerMinute(): ?float
    {
        return $this->visionScorePerMinute;
    }

    public function setVisionScorePerMinute(?float $visionScorePerMinute): static
    {
        $this->visionScorePerMinute = $visionScorePerMinute;

        return $this;
    }

    public function getGameDuration(): ?int
    {
        return $this->gameDuration;
    }

    public function setGameDuration(?int $gameDuration): static
    {
        $this->gameDuration = $gameDuration;

        return $this;
    }
}
