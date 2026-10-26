<?php

namespace App\Domain\Agent;

/** Code d'appairage mal formé, inconnu, expiré ou déjà utilisé. */
class InvalidUserCodeException extends \DomainException
{
}
