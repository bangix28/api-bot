<?php

namespace App\Infrastructure\Shared;

use App\Domain\Shared\ClockInterface;

class SystemClock implements ClockInterface
{
    public function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today');
    }
}
