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
}
