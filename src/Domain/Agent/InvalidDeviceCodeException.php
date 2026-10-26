<?php

namespace App\Domain\Agent;

/** deviceCode inconnu ou déjà échangé contre un jeton. */
class InvalidDeviceCodeException extends \DomainException
{
}
