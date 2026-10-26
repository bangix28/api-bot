<?php

namespace App\Domain\LanChallenge;

interface PlayerRunRepositoryInterface
{
    public function find(int $lanId, string $puuid): ?PlayerRun;

    /** @return PlayerRun[] tous les parcours de la LAN */
    public function findByLan(int $lanId): array;

    public function save(PlayerRun $run): void;
}
