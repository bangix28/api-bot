<?php

namespace App\Domain\EloSnapshot;

interface EloSnapshotWriteRepositoryInterface
{
    /** Dernier relevé connu pour ce compte et cette file, toutes fenêtres confondues. */
    public function lastFor(string $puuid, RankedQueueType $queue): ?EloSnapshot;

    public function add(EloSnapshot $snapshot): void;
}
