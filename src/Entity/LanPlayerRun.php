<?php

namespace App\Entity;

use App\Domain\LanChallenge\Challenge;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Parcours d'un compte dans les défis d'une LAN (Domain\LanChallenge\PlayerRun).
 * Pas de repositoryClass : la lecture passe par DoctrinePlayerRunRepository.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_lan_player_run', columns: ['lan_event_id', 'puuid'])]
class LanPlayerRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LanEvent::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LanEvent $lanEvent = null;

    #[ORM\Column(length: 255)]
    private ?string $puuid = null;

    #[ORM\Column]
    private int $rerollsLeft = 0;

    #[ORM\Column]
    private int $score = 0;

    /** @var string[]|null codes des cartes de l'offre en cours, null sans offre */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $offer = null;

    #[ORM\Column(length: 32, nullable: true, enumType: Challenge::class)]
    private ?Challenge $activeCard = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $lockedGameId = null;

    // Maudit par un saboteur : secret, jamais exposé tel quel par l'API.
    #[ORM\Column(options: ['default' => false])]
    private bool $cursed = false;

    /** @var Collection<int, LanRoundResult> */
    #[ORM\OneToMany(targetEntity: LanRoundResult::class, mappedBy: 'run', cascade: ['persist'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $results;

    public function __construct(LanEvent $lanEvent, string $puuid)
    {
        $this->lanEvent = $lanEvent;
        $this->puuid = $puuid;
        $this->results = new ArrayCollection();
    }

    public function getLanEvent(): ?LanEvent
    {
        return $this->lanEvent;
    }

    public function getPuuid(): ?string
    {
        return $this->puuid;
    }

    public function getRerollsLeft(): int
    {
        return $this->rerollsLeft;
    }

    public function setRerollsLeft(int $rerollsLeft): static
    {
        $this->rerollsLeft = $rerollsLeft;

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    /** @return string[]|null */
    public function getOffer(): ?array
    {
        return $this->offer;
    }

    /** @param string[]|null $offer */
    public function setOffer(?array $offer): static
    {
        $this->offer = $offer;

        return $this;
    }

    public function getActiveCard(): ?Challenge
    {
        return $this->activeCard;
    }

    public function setActiveCard(?Challenge $activeCard): static
    {
        $this->activeCard = $activeCard;

        return $this;
    }

    public function getLockedGameId(): ?string
    {
        return $this->lockedGameId;
    }

    public function setLockedGameId(?string $lockedGameId): static
    {
        $this->lockedGameId = $lockedGameId;

        return $this;
    }

    public function isCursed(): bool
    {
        return $this->cursed;
    }

    public function setCursed(bool $cursed): static
    {
        $this->cursed = $cursed;

        return $this;
    }

    /** @return Collection<int, LanRoundResult> */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function addResult(LanRoundResult $result): static
    {
        $this->results->add($result);

        return $this;
    }
}
