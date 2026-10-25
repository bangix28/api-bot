<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\RandomizerInterface;

/**
 * Rejoue une suite de tirages connue ; une fois épuisée, renvoie le minimum.
 * Un tirage hors bornes est une erreur du test, pas une valeur à corriger.
 */
final class ScriptedRandomizer implements RandomizerInterface
{
    /** @param int[] $values */
    public function __construct(private array $values = [])
    {
    }

    public function pickInt(int $min, int $max): int
    {
        $value = array_shift($this->values) ?? $min;

        if ($value < $min || $value > $max) {
            throw new \LogicException(sprintf('Tirage scripté %d hors de [%d ; %d].', $value, $min, $max));
        }

        return $value;
    }
}
