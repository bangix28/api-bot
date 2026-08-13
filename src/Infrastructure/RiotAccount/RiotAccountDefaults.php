<?php

namespace App\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Entity\RiotAccount;

/**
 * Valeurs de départ d'une ligne `riot_account` créée à la main (admin).
 *
 * Le formulaire ne soumet aucune colonne ranked : sans ces défauts la ligne part
 * avec des NULL partout, ce qui casse la lecture hexagonale. Écrire ici la
 * représentation normalisée « non classé » garantit qu'une ligne neuve est
 * toujours lisible, même si l'appel Riot qui suit échoue.
 */
final class RiotAccountDefaults
{
    public static function applyUnranked(RiotAccount $row): RiotAccount
    {
        // Flex laissé à NULL : l'absence signifie « jamais classé en Flex ».
        // lastUpdate laissé à NULL : « jamais rafraîchi » est une information utile.
        return $row->setSummonerRankedSoloTier(RankedTier::UNRANKED->value)
            ->setSummonerRankedSoloRank(RankedRank::UNRANKED->value)
            ->setSummonerRankedSoloLeaguePoints('0')
            ->setSummonerRankedSoloWins(0)
            ->setSummonerRankedSoloLosses('0')
            ->setScore(0)
            ->setSummonerLevel(0)
            ->setLogoId('0');
    }

    /** Nom d'invocateur de repli : la partie avant le '#' du Riot ID. */
    public static function summonerNameFrom(string $riotId): string
    {
        return trim(explode('#', $riotId, 2)[0]);
    }
}
