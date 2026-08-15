<?php

namespace App\Tests\Domain\Shared;

use App\Domain\Shared\ClockInterface;

final readonly class FixedClock implements ClockInterface
{
    public function __construct(private \DateTimeImmutable $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function today(): \DateTimeImmutable
    {
        return $this->now->setTime(0, 0);
    }
}
