<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Session d'un joueur (Domain\Player\PlayerSession). Seule l'empreinte du jeton est stockée. */
#[ORM\Entity]
#[ORM\Index(name: 'idx_player_refresh_token_owner', columns: ['discord_id'])]
class PlayerRefreshToken
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

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    public function __construct(string $digest, string $discordId, string $discordUsername, \DateTimeImmutable $expiresAt)
    {
        $this->digest = $digest;
        $this->discordId = $discordId;
        $this->discordUsername = $discordUsername;
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

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function expireAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}
