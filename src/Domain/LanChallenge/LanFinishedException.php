<?php

namespace App\Domain\LanChallenge;

/** La LAN est terminée : plus aucune carte ne peut être tirée, choisie ou relancée. */
class LanFinishedException extends \DomainException
{
}
