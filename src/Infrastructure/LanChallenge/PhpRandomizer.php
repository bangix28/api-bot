<?php

namespace App\Infrastructure\LanChallenge;

use App\Domain\LanChallenge\RandomizerInterface;
use Random\Engine\Secure;
use Random\Randomizer;

/**
 * Tirage par le moteur sûr de PHP : une offre ne doit pas être prévisible
 * par un joueur qui observerait les précédentes.
 */
final readonly class PhpRandomizer implements RandomizerInterface
{
    private Randomizer $randomizer;

    public function __construct()
    {
        $this->randomizer = new Randomizer(new Secure());
    }

    public function pickInt(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }
}
