<?php

namespace App\Infrastructure\Security;

use App\Domain\Player\DiscordIdentity;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\PayloadAwareUserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Reconstruit un joueur à partir de son JWT, sans base de données : un joueur
 * n'existe que par son identité Discord, portée par le jeton.
 *
 * Chaîné après le fournisseur des comptes d'administration (security.yaml) :
 * il ne répond qu'aux identifiants « discord:<id> » et laisse passer les e-mails.
 */
final class PlayerUserProvider implements PayloadAwareUserProviderInterface
{
    /** Claim du JWT qui porte le nom d'utilisateur Discord. */
    public const string USERNAME_CLAIM = 'discordUsername';

    public function loadUserByIdentifierAndPayload(string $identifier, array $payload): UserInterface
    {
        $discordId = PlayerUser::discordIdFrom($identifier)
            ?? throw new UserNotFoundException(sprintf('« %s » n\'est pas un identifiant de joueur.', $identifier));

        $username = $payload[self::USERNAME_CLAIM] ?? null;
        if (!is_string($username)) {
            throw new UserNotFoundException('Jeton de joueur sans nom d\'utilisateur Discord.');
        }

        try {
            return new PlayerUser(new DiscordIdentity($discordId, $username));
        } catch (\InvalidArgumentException $exception) {
            throw new UserNotFoundException('Jeton de joueur invalide.', previous: $exception);
        }
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Sans le contenu du jeton, on ne connaît pas le nom du joueur :
        // seul le chargement par JWT est pris en charge.
        throw new UserNotFoundException(sprintf('Un joueur ne se charge que depuis son jeton (« %s »).', $identifier));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        // Firewall stateless : rien à rafraîchir depuis une session.
        throw new UnsupportedUserException('Un joueur ne vit pas en session.');
    }

    public function supportsClass(string $class): bool
    {
        return $class === PlayerUser::class;
    }
}
