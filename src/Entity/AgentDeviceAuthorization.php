<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande d'appairage d'un agent (Domain\Agent\DeviceAuthorization).
 * Seule l'empreinte du deviceCode est stockée : la base ne contient aucun
 * secret utilisable.
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_agent_device_user_code', columns: ['user_code'])]
class AgentDeviceAuthorization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $deviceCodeDigest;

    #[ORM\Column(length: 8)]
    private string $userCode;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastPolledAt = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $approvedDiscordId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $approvedDiscordUsername = null;

    #[ORM\ManyToOne(targetEntity: LanEvent::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?LanEvent $approvedLanEvent = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $consumed = false;

    public function __construct(string $deviceCodeDigest)
    {
        $this->deviceCodeDigest = $deviceCodeDigest;
    }

    public function getDeviceCodeDigest(): string
    {
        return $this->deviceCodeDigest;
    }

    public function getUserCode(): string
    {
        return $this->userCode;
    }

    public function setUserCode(string $userCode): static
    {
        $this->userCode = $userCode;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getLastPolledAt(): ?\DateTimeImmutable
    {
        return $this->lastPolledAt;
    }

    public function setLastPolledAt(?\DateTimeImmutable $lastPolledAt): static
    {
        $this->lastPolledAt = $lastPolledAt;

        return $this;
    }

    public function getApprovedDiscordId(): ?string
    {
        return $this->approvedDiscordId;
    }

    public function getApprovedDiscordUsername(): ?string
    {
        return $this->approvedDiscordUsername;
    }

    public function getApprovedLanEvent(): ?LanEvent
    {
        return $this->approvedLanEvent;
    }

    public function setApproval(?string $discordId, ?string $discordUsername, ?LanEvent $lanEvent): static
    {
        $this->approvedDiscordId = $discordId;
        $this->approvedDiscordUsername = $discordUsername;
        $this->approvedLanEvent = $lanEvent;

        return $this;
    }

    public function isConsumed(): bool
    {
        return $this->consumed;
    }

    public function setConsumed(bool $consumed): static
    {
        $this->consumed = $consumed;

        return $this;
    }
}
