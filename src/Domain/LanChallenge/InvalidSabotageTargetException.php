<?php

namespace App\Domain\LanChallenge;

/** Cible impossible : soi-même, hors de la LAN, ou pas encore inscrite aux défis. */
class InvalidSabotageTargetException extends \InvalidArgumentException
{
}
