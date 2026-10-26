<?php

namespace App\Entity;

use App\Domain\LanChallenge\Challenge;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un sabotage joué pendant une LAN (Domain\LanChallenge\Sabotage). Le saboteur
 * est conservé pour l'historique et l'administration, jamais exposé par l'API.
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_lan_sabotage_occurred_at', columns: ['occurred_at'])]
class LanSabotage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LanEvent::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LanEvent $lanEvent;

    #[ORM\Column(length: 32, enumType: Challenge::class)]
    private Challenge $card;

    #[ORM\Column(length: 255)]
    private string $saboteurPuuid;

    #[ORM\Column(length: 255)]
    private string $targetPuuid;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    public function __construct(LanEvent $lanEvent, Challenge $card, string $saboteurPuuid, string $targetPuuid, \DateTimeImmutable $occurredAt)
    {
        $this->lanEvent = $lanEvent;
        $this->card = $card;
        $this->saboteurPuuid = $saboteurPuuid;
        $this->targetPuuid = $targetPuuid;
        $this->occurredAt = $occurredAt;
    }

    public function getCard(): Challenge
    {
        return $this->card;
    }

    public function getSaboteurPuuid(): string
    {
        return $this->saboteurPuuid;
    }

    public function getTargetPuuid(): string
    {
        return $this->targetPuuid;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
