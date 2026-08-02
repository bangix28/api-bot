<?php

namespace App\Application\EloSnapshot\SnapshotDailyElo;

readonly class SnapshotSummary
{
    public function __construct(
        public int $ok,
        public int $failed,
        public int $skipped,
    ) {
    }
}
