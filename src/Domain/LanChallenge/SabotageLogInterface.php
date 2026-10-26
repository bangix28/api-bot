<?php

namespace App\Domain\LanChallenge;

interface SabotageLogInterface
{
    /** Garde la trace d'un sabotage : fil d'exploits (s'il est visible) et historique de la LAN. */
    public function record(int $lanId, Sabotage $sabotage): void;
}
