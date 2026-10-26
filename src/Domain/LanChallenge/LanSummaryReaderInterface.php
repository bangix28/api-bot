<?php

namespace App\Domain\LanChallenge;

interface LanSummaryReaderInterface
{
    public function find(int $lanId): ?LanSummary;
}
