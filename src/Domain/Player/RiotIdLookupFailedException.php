<?php

namespace App\Domain\Player;

/** Riot n'a pas pu donner le Riot ID d'un compte : on garde celui qu'on connaît. */
class RiotIdLookupFailedException extends \RuntimeException
{
}
