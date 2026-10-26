<?php

namespace App\Domain\Player;

/** Aucun compte suivi ne porte ce Riot ID : un admin doit d'abord l'ajouter. */
class RiotAccountNotTrackedException extends \DomainException
{
}
