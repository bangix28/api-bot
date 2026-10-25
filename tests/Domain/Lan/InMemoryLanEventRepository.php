<?php

namespace App\Tests\Domain\Lan;

use App\Domain\Lan\LanEvent;
use App\Domain\Lan\LanEventRepositoryInterface;

final readonly class InMemoryLanEventRepository implements LanEventRepositoryInterface
{
    /** @param LanEvent[] $lanEvents */
    public function __construct(private array $lanEvents = [])
    {
    }

    /** @return LanEvent[] */
    public function findInProgressAt(\DateTimeImmutable $instant): array
    {
        return array_values(array_filter(
            $this->lanEvents,
            static fn(LanEvent $lan) => $lan->isInProgressAt($instant),
        ));
    }
}
