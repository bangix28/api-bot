<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\RiotAccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Validator as AcmeAssert;

#[ORM\Entity(repositoryClass: RiotAccountRepository::class)]
#[UniqueEntity(
    fields: ['riotId'],
    message: 'Vous êtes déja inscrit avec ce Riot ID !'
)]
/**
 * Récupère les détails d’un invocateur précis
 */
#[ApiResource(
    uriTemplate: '/riot-account/account/{id}',
    operations: [ new Get ],
    normalizationContext: ['groups' => ['riotAccount:read:get']]
)]
/**
 * Récupère tous les invocateurs ainsi que leurs détails.
 */
#[ApiResource(
    uriTemplate: '/riot-account/ranked',
    operations: [ new GetCollection ],
    normalizationContext: ['groups' => ['riotAccount:read:get']],
)]
#[ApiFilter(OrderFilter::class, properties: ['score' => 'DESC'])]

class RiotAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['riotAccount:read:get'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['riotAccount:read:get','riotAccount:write'])]
    private ?string $riotId = null;
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

    #[ORM\Column(nullable: true)]
    #[Groups(['riotAccount:read:get'])]
    private ?int $summoner_ranked_solo_wins = null;

    /**
     * @var Collection<int, HistoryAccountLol>
     */
    #[ORM\OneToMany(mappedBy: 'riotAccount', targetEntity: HistoryAccountLol::class, orphanRemoval: true)]
    private Collection $historyAccountLols;

    #[ORM\Column(length: 255)]
    #[Groups(['riotAccount:read:get'])]
    private ?string $logoId = null;

    /**
     * @var Collection<int, SummonerEloDaily>
     */
    #[ORM\OneToMany(mappedBy: 'riotAccount', targetEntity: SummonerEloDaily::class)]
    private Collection $summonerEloDailies;

    public function __construct()
    {
        $this->historyAccountLols = new ArrayCollection();
        $this->summonerEloDailies = new ArrayCollection();
    }


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

    public function getSummonerRankedSoloWins(): ?int
    {
        return $this->summoner_ranked_solo_wins;
    }

    public function setSummonerRankedSoloWins(?int $summoner_ranked_solo_wins): static
    {
        $this->summoner_ranked_solo_wins = $summoner_ranked_solo_wins;

        return $this;
    }

    /**
     * @return Collection<int, HistoryAccountLol>
     */
    public function getHistoryAccountLols(): Collection
    {
        return $this->historyAccountLols;
    }

    public function addHistoryAccountLol(HistoryAccountLol $historyAccountLol): static
    {
        if (!$this->historyAccountLols->contains($historyAccountLol)) {
            $this->historyAccountLols->add($historyAccountLol);
            $historyAccountLol->setRiotAccountId($this);
        }

        return $this;
    }

    public function removeHistoryAccountLol(HistoryAccountLol $historyAccountLol): static
    {
        if ($this->historyAccountLols->removeElement($historyAccountLol)) {
            // set the owning side to null (unless already changed)
            if ($historyAccountLol->getRiotAccountId() === $this) {
                $historyAccountLol->setRiotAccountId(null);
            }
        }

        return $this;
    }

    public function getLogoId(): ?string
    {
        return $this->logoId;
    }

    public function setLogoId(string $logoId): static
    {
        $this->logoId = $logoId;

        return $this;
    }

    /**
     * @return Collection<int, SummonerEloDaily>
     */
    public function getSummonerEloDailies(): Collection
    {
        return $this->summonerEloDailies;
    }

    public function addSummonerEloDaily(SummonerEloDaily $summonerEloDaily): static
    {
        if (!$this->summonerEloDailies->contains($summonerEloDaily)) {
            $this->summonerEloDailies->add($summonerEloDaily);
            $summonerEloDaily->setRiotAccount($this);
        }

        return $this;
    }

    public function removeSummonerEloDaily(SummonerEloDaily $summonerEloDaily): static
    {
        if ($this->summonerEloDailies->removeElement($summonerEloDaily)) {
            // set the owning side to null (unless already changed)
            if ($summonerEloDaily->getRiotAccount() === $this) {
                $summonerEloDaily->setRiotAccount(null);
            }
        }

        return $this;
    }

}
