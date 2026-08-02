<?php

namespace App\Domain\RankedRace;

interface RaceEventRepositoryInterface
{
    /** @return RaceEvent[] triés par date de début décroissante (le plus récent d'abord) */
    public function findAll(): array;

    public function findById(int $id): ?RaceEvent;
}
