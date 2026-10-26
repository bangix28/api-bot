<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace d'un relevé reçu d'un agent. Seul le relevé final garde son contenu
 * (preuve du règlement) : les relevés intermédiaires, toutes les 15 s, ne
 * servent qu'au direct et pèseraient lourd.
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_agent_report_session', columns: ['token_digest', 'session_id'])]
#[ORM\Index(name: 'idx_agent_report_received_at', columns: ['token_digest', 'received_at'])]
class AgentReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Empreinte du jeton de l'agent émetteur. */
    #[ORM\Column(length: 64)]
    private string $tokenDigest;

    #[ORM\Column(length: 64)]
    private string $sessionId;

    #[ORM\Column]
    private int $sequence;

    #[ORM\ManyToOne(targetEntity: LanGameRecord::class)]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'game_id', nullable: false, onDelete: 'CASCADE')]
    private LanGameRecord $game;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $finalPayload;

    public function __construct(string $tokenDigest, string $sessionId, int $sequence, LanGameRecord $game, \DateTimeImmutable $receivedAt, ?string $finalPayload)
    {
        $this->tokenDigest = $tokenDigest;
        $this->sessionId = $sessionId;
        $this->sequence = $sequence;
        $this->game = $game;
        $this->receivedAt = $receivedAt;
        $this->finalPayload = $finalPayload;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getGame(): LanGameRecord
    {
        return $this->game;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
