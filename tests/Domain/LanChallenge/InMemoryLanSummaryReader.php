<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\LanSummary;
use App\Domain\LanChallenge\LanSummaryReaderInterface;

final class InMemoryLanSummaryReader implements LanSummaryReaderInterface
{
    /** @var array<int, LanSummary> */
    private array $lans = [];

    public function add(LanSummary $lan): self
    {
        $this->lans[$lan->lanId] = $lan;

        return $this;
    }

    public function find(int $lanId): ?LanSummary
    {
        return $this->lans[$lanId] ?? null;
    }

    public function findCurrentOrNextFor(array $puuids, \DateTimeImmutable $now): ?LanSummary
    {
        $candidates = array_filter(
            $this->lans,
            static fn(LanSummary $lan) => !$lan->isFinishedAt($now)
                && array_any($puuids, static fn(string $puuid) => $lan->hasParticipant($puuid)),
        );
        usort($candidates, static fn(LanSummary $left, LanSummary $right) => $left->startsAt <=> $right->startsAt);

        return $candidates[0] ?? null;
    }
}
