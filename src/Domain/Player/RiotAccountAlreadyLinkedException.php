<?php

namespace App\Domain\Player;

/** Le compte Riot est déjà lié à un autre joueur. */
class RiotAccountAlreadyLinkedException extends \DomainException
{
}
