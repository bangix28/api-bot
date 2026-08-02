<?php

namespace App\Tests\Domain\Shared;

use App\Domain\Shared\ClockInterface;

final readonly class FixedClock implements ClockInterface
{
    public function __construct(private \DateTimeImmutable $today)
    {
    }

    public function today(): \DateTimeImmutable
    {
        return $this->today;
    }
}
