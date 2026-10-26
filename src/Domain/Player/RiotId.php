<?php

namespace App\Domain\Player;

/**
 * Riot ID saisi par un joueur : « Pseudo#TAG ».
 * Seule la forme est vérifiée ici ; l'existence du compte, c'est la base qui la dit.
 */
final readonly class RiotId
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        $parts = explode('#', $value);

        if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
            throw new InvalidRiotIdException(sprintf('« %s » n\'est pas un Riot ID : attendu « Pseudo#TAG ».', $value));
        }

        $this->value = $value;
    }
}
