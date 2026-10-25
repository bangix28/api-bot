<?php

namespace App\Domain\LanChallenge;

/**
 * Port de tirage aléatoire : les tests rejouent une séquence connue,
 * la prod s'appuie sur un générateur cryptographiquement sûr.
 */
interface RandomizerInterface
{
    /** Entier uniformément tiré entre $min et $max inclus. */
    public function pickInt(int $min, int $max): int;
}
