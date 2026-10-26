<?php

namespace App\Domain\LanChallenge;

/** Plusieurs comptes du joueur participent à la même LAN : impossible de savoir lequel joue. */
class SeveralAccountsInLanException extends \DomainException
{
}
