<?php

namespace App\Domain\Player;

/** Jeton de rafraîchissement inconnu ou expiré : le joueur doit se reconnecter avec Discord. */
class InvalidRefreshTokenException extends \RuntimeException
{
}
