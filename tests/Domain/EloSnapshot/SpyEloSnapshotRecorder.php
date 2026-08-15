<?php

namespace App\Tests\Domain\EloSnapshot;

use App\Domain\EloSnapshot\EloSnapshotRecorderInterface;
use App\Domain\EloSnapshot\RankedQueuesSnapshot;

/**
 * Enregistre les appels reçus, et peut simuler une panne d'écriture pour
 * vérifier qu'elle ne contamine pas le refresh du compte.
 */
final class SpyEloSnapshotRecorder implements EloSnapshotRecorderInterface
{
    /** @var array{puuid: string, queues: RankedQueuesSnapshot, observedAt: \DateTimeImmutable}[] */
    public array $calls = [];

    public function __construct(private readonly bool $throws = false)
    {
    }

    public function record(string $puuid, RankedQueuesSnapshot $queues, \DateTimeImmutable $observedAt): void
    {
        $this->calls[] = ['puuid' => $puuid, 'queues' => $queues, 'observedAt' => $observedAt];

        if ($this->throws) {
            throw new \RuntimeException('écriture du point de course indisponible');
        }
    }

    /** @return string[] */
    public function recordedPuuids(): array
    {
        return array_column($this->calls, 'puuid');
    }
}
