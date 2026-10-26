<?php

namespace App\Entity;

use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\Verdict;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Une manche réglée d'un parcours (Domain\LanChallenge\RoundResult), avec son heure de règlement. */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_lan_round_position', columns: ['run_id', 'position'])]
#[ORM\Index(name: 'idx_lan_round_settled_at', columns: ['settled_at'])]
class LanRoundResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LanPlayerRun::class, inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LanPlayerRun $run;

    /** Rang de la manche dans le parcours, à partir de 0 : l'historique est rejoué dans cet ordre. */
    #[ORM\Column]
    private int $position;

    #[ORM\Column(length: 64)]
    private string $gameId;

    #[ORM\Column(length: 32, enumType: Challenge::class)]
    private Challenge $card;

    #[ORM\Column(length: 16, enumType: Verdict::class)]
    private Verdict $verdict;

    #[ORM\Column]
    private int $points;

    #[ORM\Column]
    private bool $roundBonus;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $settledAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $curseApplied;

    public function __construct(
        LanPlayerRun $run,
        int $position,
        string $gameId,
        Challenge $card,
        Verdict $verdict,
        int $points,
        bool $roundBonus,
        \DateTimeImmutable $settledAt,
        bool $curseApplied = false,
    ) {
        $this->run = $run;
        $this->position = $position;
        $this->gameId = $gameId;
        $this->card = $card;
        $this->verdict = $verdict;
        $this->points = $points;
        $this->roundBonus = $roundBonus;
        $this->settledAt = $settledAt;
        $this->curseApplied = $curseApplied;
    }

    public function getRun(): LanPlayerRun
    {
        return $this->run;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getGameId(): string
    {
        return $this->gameId;
    }

    public function getCard(): Challenge
    {
        return $this->card;
    }

    public function getVerdict(): Verdict
    {
        return $this->verdict;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function isRoundBonus(): bool
    {
        return $this->roundBonus;
    }

    public function getSettledAt(): \DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function isCurseApplied(): bool
    {
        return $this->curseApplied;
    }
}
