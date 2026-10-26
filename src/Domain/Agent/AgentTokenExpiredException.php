<?php

namespace App\Domain\Agent;

/** Jeton d'agent dont la LAN est terminée (hors grâce du relevé final). */
class AgentTokenExpiredException extends \DomainException
{
}
