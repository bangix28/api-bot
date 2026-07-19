<?php

namespace App\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountRefreshData;
use App\Domain\RiotAccount\RiotApiClientInterface;
use App\Domain\RiotAccount\SummonerRankedEntity;
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

        // 2. entrées ranked → tableau ; trouver celle où queueType === 'RANKED_SOLO_5x5'
        $entries = $this->validationController->getRankedsInformationsById($puuid);

        $soloEntry = array_find(
            $entries,
            static fn($entry) => $entry->queueType === 'RANKED_SOLO_5x5',
        );

        if ($soloEntry === null) {
            $ranked = new SummonerRankedEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0);
        } else {
            $ranked = new SummonerRankedEntity(
                RankedRank::fromString($soloEntry->rank),
                RankedTier::fromString($soloEntry->tier),
                (int) $soloEntry->leaguePoints,
                (int) $soloEntry->wins,
                (int) $soloEntry->losses,
            );
        }

        // 4. retourner le DTO
        return new RiotAccountRefreshData(
            $ranked,
            (int) ($summoner->summonerLevel ?? 0),
            (string) ($summoner->profileIconId ?? 0),
        );
    }
}