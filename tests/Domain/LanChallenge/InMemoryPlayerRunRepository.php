<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;

final class InMemoryPlayerRunRepository implements PlayerRunRepositoryInterface
{
    /** @var array<string, PlayerRun> */
    private array $runs = [];

    public function find(int $lanId, string $puuid): ?PlayerRun
    {
        return $this->runs[$lanId . '|' . $puuid] ?? null;
    }

    /** @return PlayerRun[] */
    public function findByLan(int $lanId): array
    {
        return array_values(array_filter($this->runs, static fn(PlayerRun $run) => $run->lanId === $lanId));
    }

    public function save(PlayerRun $run): void
    {
        $this->runs[$run->lanId . '|' . $run->puuid] = $run;
    }
}
