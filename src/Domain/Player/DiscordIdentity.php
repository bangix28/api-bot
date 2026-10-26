<?php

namespace App\Domain\Player;

/**
 * Identité Discord d'un joueur, telle que Discord la renvoie après connexion.
 * L'identifiant (« snowflake ») est stable ; le nom d'utilisateur peut changer.
 */
final readonly class DiscordIdentity
{
    public function __construct(
        public string $userId,
        public string $username,
    ) {
        if (!ctype_digit($userId)) {
            throw new \InvalidArgumentException('Un identifiant Discord est une suite de chiffres.');
        }

        if (trim($username) === '') {
            throw new \InvalidArgumentException('Un nom d\'utilisateur Discord ne peut pas être vide.');
        }
    }
}
