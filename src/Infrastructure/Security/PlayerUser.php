<?php

namespace App\Infrastructure\Security;

use App\Domain\Player\DiscordIdentity;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Utilisateur de sécurité d'un joueur connecté par Discord. Jamais persisté :
 * il est reconstruit à chaque requête à partir de son jeton (PlayerUserProvider).
 * Les comptes d'administration, eux, restent des App\Entity\User.
 */
final readonly class PlayerUser implements UserInterface
{
    public const string ROLE = 'ROLE_PLAYER';
    private const string IDENTIFIER_PREFIX = 'discord:';

    public function __construct(public DiscordIdentity $discord)
    {
    }

    public function getUserIdentifier(): string
    {
        return self::IDENTIFIER_PREFIX . $this->discord->userId;
    }

    /** Identifiant Discord contenu dans un identifiant de joueur, null si ce n'en est pas un. */
    public static function discordIdFrom(string $identifier): ?string
    {
        return str_starts_with($identifier, self::IDENTIFIER_PREFIX)
            ? substr($identifier, strlen(self::IDENTIFIER_PREFIX))
            : null;
    }

    public function getRoles(): array
    {
        return [self::ROLE];
    }

    public function eraseCredentials(): void
    {
    }
}
