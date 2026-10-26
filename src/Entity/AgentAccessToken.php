<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton d'un agent appairé (Domain\Agent\AgentToken). Seule son empreinte est
 * stockée. Révoqué plutôt que supprimé : l'historique dit quel agent a envoyé
 * quel relevé.
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_agent_token_owner', columns: ['discord_id', 'lan_event_id'])]
class AgentAccessToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $digest;

    #[ORM\Column(length: 32)]
    private string $discordId;

    #[ORM\Column(length: 64)]
    private string $discordUsername;

    #[ORM\ManyToOne(targetEntity: LanEvent::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LanEvent $lanEvent;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(string $digest, string $discordId, string $discordUsername, LanEvent $lanEvent, \DateTimeImmutable $expiresAt)
    {
        $this->digest = $digest;
        $this->discordId = $discordId;
        $this->discordUsername = $discordUsername;
        $this->lanEvent = $lanEvent;
        $this->expiresAt = $expiresAt;
    }

    public function getDigest(): string
    {
        return $this->digest;
    }

    public function getDiscordId(): string
    {
        return $this->discordId;
    }

    public function getDiscordUsername(): string
    {
        return $this->discordUsername;
    }

    public function getLanEvent(): LanEvent
    {
        return $this->lanEvent;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(\DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt ??= $revokedAt;

        return $this;
    }
}
