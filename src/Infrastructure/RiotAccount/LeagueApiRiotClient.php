<?php

namespace App\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountRefreshData;
use App\Domain\RiotAccount\RiotApiClientInterface;
use App\Infrastructure\Riot\RiotApiGateway;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;

class LeagueApiRiotClient implements RiotApiClientInterface
{

    public function __construct(private RiotApiGateway $validationController)
    {
    }

    /**
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getAccount(string $puuid): RiotAccountRefreshData
    {
        $summoner = $this->validationController->getSummonerAcountsDetails($puuid);

        // entrées ranked → une par file (SoloQ, Flex...)
        $entries = $this->validationController->getRankedsInformationsById($puuid);

        $soloEntry = array_find(
            $entries,
            static fn($entry) => $entry->queueType === 'RANKED_SOLO_5x5',
        );

        $flexEntry = array_find(
            $entries,
            static fn($entry) => $entry->queueType === 'RANKED_FLEX_SR',
        );

        // SoloQ : UNRANKED par défaut (le score du classement en dépend).
        // Flex : null si non classé — l'absence est une info, pas un UNRANKED synthétique.
        $rankedSolo = LeagueEntryMapper::map($soloEntry)
            ?? new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0);

        return new RiotAccountRefreshData(
            $rankedSolo,
            LeagueEntryMapper::map($flexEntry),
            (int) ($summoner->summonerLevel ?? 0),
            (string) ($summoner->profileIconId ?? 0),
        );
    }
}
