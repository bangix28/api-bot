<?php

namespace App\Domain\Player;

/** La connexion Discord a échoué : code refusé ou expiré, ou Discord injoignable. */
class DiscordLoginFailedException extends \RuntimeException
{
}
