<?php

namespace App\Domain\Player;

/**
 * Riot ID actuel d'un compte chez Riot. Le puuid ne change jamais ; le Riot ID
 * (« Pseudo#TAG »), si : un joueur peut changer de pseudo à tout moment.
 */
interface RiotIdDirectoryInterface
{
    /**
     * @return RiotId|null null si Riot ne connaît pas ce puuid
     *
     * @throws RiotIdLookupFailedException Riot injoignable, en erreur ou qui limite les appels
     */
    public function currentRiotIdOf(string $puuid): ?RiotId;
}
