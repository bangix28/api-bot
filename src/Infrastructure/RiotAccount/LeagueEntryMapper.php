<?php

namespace App\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\MiniSeries;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use RiotAPI\LeagueAPI\Objects\LeagueEntryDto;

/**
 * Traduit une entrée League-V4 (DTO du SDK Riot) en VO domaine.
 * Partagé entre le refresh de compte et le snapshot elo quotidien.
 */
final class LeagueEntryMapper
{
    public static function map(?LeagueEntryDto $entry): ?RankedQueueEntity
    {
        if ($entry === null) {
            return null;
        }

        $miniSeries = null;
        if (isset($entry->miniSeries)) {
            $miniSeries = new MiniSeries(
                (int) $entry->miniSeries->wins,
                (int) $entry->miniSeries->losses,
                (int) $entry->miniSeries->target,
                (string) $entry->miniSeries->progress,
            );
        }

        return new RankedQueueEntity(
            RankedRank::fromString($entry->rank),
            RankedTier::fromString($entry->tier),
            (int) $entry->leaguePoints,
            (int) $entry->wins,
            (int) $entry->losses,
            (bool) ($entry->hotStreak ?? false),
            (bool) ($entry->veteran ?? false),
            (bool) ($entry->freshBlood ?? false),
            $miniSeries,
        );
    }
}
