<?php

namespace App\Infrastructure\Security;

use App\Domain\Agent\AgentToken;
use Symfony\Component\Security\Core\User\UserInterface;

/** Utilisateur de sécurité d'un agent : reconstruit à chaque requête depuis son jeton. */
final readonly class AgentUser implements UserInterface
{
    public const string ROLE = 'ROLE_AGENT';

    public function __construct(public AgentToken $token)
    {
    }

    public function getUserIdentifier(): string
    {
        // Jamais le jeton lui-même : l'identifiant peut finir dans un log.
        return 'agent:' . substr($this->token->digest, 0, 16);
    }

    public function getRoles(): array
    {
        return [self::ROLE];
    }

    public function eraseCredentials(): void
    {
    }
}
