<?php

namespace App\Domain\Shared;

/**
 * Port horloge : permet de figer le temps dans les tests
 * (pas de symfony/clock dans le projet — un port domaine suffit).
 */
interface ClockInterface
{
    /** Instant courant, fuseau explicite côté adaptateur. */
    public function now(): \DateTimeImmutable;

    /** Minuit du jour courant — dérivé de now(), donc même fuseau. */
    public function today(): \DateTimeImmutable;
}
