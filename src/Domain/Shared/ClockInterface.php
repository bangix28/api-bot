<?php

namespace App\Domain\Shared;

/**
 * Port horloge : permet de figer « aujourd'hui » dans les tests
 * (pas de symfony/clock dans le projet — un port domaine suffit).
 */
interface ClockInterface
{
    public function today(): \DateTimeImmutable;
}
