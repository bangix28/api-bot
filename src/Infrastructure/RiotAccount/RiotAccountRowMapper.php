<?php

namespace App\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\MiniSeries;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountEntity;
use App\Entity\RiotAccount;

/**
 * Traduit une ligne Doctrine `riot_account` en entité de domaine.
 *
 * Toutes les colonnes sont nullables en base (historique) alors que le domaine
 * exige des types stricts : ce mapper est la frontière qui absorbe cet écart.
 * Un NULL en solo est lu comme la représentation « non classé »
 * (cf. migration Version20260628111804 et RankedTier/RankedRank::UNRANKED),
 * jamais propagé tel quel — sinon `fromString(null)` lève un TypeError.
 */
final class RiotAccountRowMapper
{
    public static function map(RiotAccount $row): RiotAccountEntity
    {
        return new RiotAccountEntity(
            $row->getRiotId() ?? '',
            $row->getPuuid() ?? '',
            $row->getSummonerName() ?? '',
            self::mapSolo($row),
            $row->getSummonerLevel() ?? 0,
            $row->getLogoId() ?? '0',
            self::mapFlex($row),
        );
    }

    private static function mapSolo(RiotAccount $row): RankedQueueEntity
    {
        // fromString('') rend UNRANKED : une colonne vide ou NULL décrit un compte non classé.
        return new RankedQueueEntity(
            RankedRank::fromString($row->getSummonerRankedSoloRank() ?? ''),
            RankedTier::fromString($row->getSummonerRankedSoloTier() ?? ''),
            (int) $row->getSummonerRankedSoloLeaguePoints(),
            $row->getSummonerRankedSoloWins() ?? 0,
            (int) $row->getSummonerRankedSoloLosses(),
            (bool) $row->getSoloHotStreak(),
            (bool) $row->getSoloVeteran(),
            (bool) $row->getSoloFreshBlood(),
            self::mapMiniSeries($row),
        );
    }

    private static function mapFlex(RiotAccount $row): ?RankedQueueEntity
    {
        // Tout ou rien : l'absence de flex est une info (« jamais classé en Flex »),
        // pas un UNRANKED synthétique — même choix que LeagueApiRiotClient.
        // Un bloc partiel (tier sans rang) est donc lu comme une absence ; le
        // prochain refresh réécrit les 5 colonnes d'un coup.
        if (($row->getSummonerRankedFlexTier() ?? '') === '' || ($row->getSummonerRankedFlexRank() ?? '') === '') {
            return null;
        }

        return new RankedQueueEntity(
            RankedRank::fromString($row->getSummonerRankedFlexRank()),
            RankedTier::fromString($row->getSummonerRankedFlexTier()),
            (int) $row->getSummonerRankedFlexLeaguePoints(),
            (int) $row->getSummonerRankedFlexWins(),
            (int) $row->getSummonerRankedFlexLosses(),
        );
    }

    private static function mapMiniSeries(RiotAccount $row): ?MiniSeries
    {
        if ($row->getSoloMiniSeriesTarget() === null) {
            return null;
        }

        return new MiniSeries(
            (int) $row->getSoloMiniSeriesWins(),
            (int) $row->getSoloMiniSeriesLosses(),
            $row->getSoloMiniSeriesTarget(),
            (string) $row->getSoloMiniSeriesProgress(),
        );
    }
}
