<?php

namespace App\Domain\Agent;

/** Aucun compte du joueur ne participe à une LAN en cours ou à venir. */
class NotRegisteredInAnyLanException extends \DomainException
{
}
