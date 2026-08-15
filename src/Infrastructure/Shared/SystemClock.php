<?php

namespace App\Infrastructure\Shared;

use App\Domain\Shared\ClockInterface;

class SystemClock implements ClockInterface
{
    /**
     * Fuseau explicite et non celui de PHP : les fenêtres de course sont des
     * bornes civiles (lundi 00:00 à Paris). Sur un serveur en UTC, une game
     * jouée dimanche 23h30 tomberait sinon dans la semaine suivante.
     */
    public function __construct(private readonly string $timezone = 'Europe/Paris')
    {
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));
    }

    public function today(): \DateTimeImmutable
    {
        return $this->now()->setTime(0, 0);
    }
}
