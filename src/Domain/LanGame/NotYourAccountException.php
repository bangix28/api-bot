<?php

namespace App\Domain\LanGame;

/** Le relevé porte sur la partie d'un compte qui n'appartient pas au joueur de l'agent. */
class NotYourAccountException extends \DomainException
{
}
