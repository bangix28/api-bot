<?php

namespace App\Infrastructure\MatchHistory;

use App\Domain\MatchHistory\MatchData;
use App\Domain\MatchHistory\ParticipantData;
use App\Domain\MatchHistory\RiotMatchApiClientInterface;
use App\Infrastructure\Riot\RiotApiGateway;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;

class LeagueApiRiotMatchClient implements RiotMatchApiClientInterface
{
    public function __construct(private RiotApiGateway $riotApiGateway)
    {

    }

    /**
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getMatchIds(string $puuid, ?int $since): array
    {
        return $this->riotApiGateway->getListIdMatchHistoryLol($puuid, $since);
    }

    /**
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getMatch(string $matchId): MatchData
    {
       $match = $this->riotApiGateway->getDataMatchById($matchId);

        $participants = array_map(
            fn(array $p) => new ParticipantData($p->puuid, $p->win, $p->championId, $p->kills, $p->deaths, $p->assists),
            $match->info->participants
        );

        return new MatchData(
            $match->info->gameEndTimestamp,
            $match->info->gameDuration,
            $participants
        );
    }
}