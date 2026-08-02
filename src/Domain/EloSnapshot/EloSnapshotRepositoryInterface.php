<?php

namespace App\Domain\EloSnapshot;

interface EloSnapshotRepositoryInterface
{
    /**
     * Un snapshot existe-t-il déjà pour ce compte ce jour-là (toutes files) ?
     * Sert l'idempotence : si oui, on ne rappelle pas l'API Riot.
     */
    public function existsFor(string $puuid, \DateTimeImmutable $day): bool;

    public function add(DailyEloSnapshot $snapshot): void;
}
