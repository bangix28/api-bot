<?php

namespace App\Infrastructure\Riot;

use App\Enum\RiotApiEnum;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;
use RiotAPI\LeagueAPI\Objects\MatchDto;
use RiotAPI\LeagueAPI\Objects\SummonerDto;

readonly class RiotApiGateway
{
   public function __construct(private RiotApiClient $riotApi)
   {}


    /**
     * @throws ServerLimitException
     * @throws ServerException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getRankedsInformationsById($summonerId): ?array
    {
        return $this->riotApi->riotApiInit()->getLeagueEntriesForSummoner($summonerId);
   }

    /**
     * @param $summonerId
     * @return SummonerDto|null
     * @throws GeneralException
     * @throws RequestException
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     */
   public function getSummonerAcountsDetails($summonerId): ?SummonerDto
   {
       $callApiRiot =  $this->riotApi->riotApiInit()->getSummonerByPUUID($summonerId);
       return $callApiRiot;
   }
    /**
     * @return array
     * @throws GeneralException
     * @throws RequestException
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     * Obtiens la liste des matchs d'un compte Lol en utilisant son PUUID
     */
   public function getListIdMatchHistoryLol(string $puuid, ?int $startTime = null): array
   {
       $callApiRiot = $this->riotApi->riotApiInit()->getMatchIdsByPUUID($puuid,RiotApiEnum::QUEUE_TYPE_RANKED_SOLO->value,null,RiotApiEnum::START_INDEX->value,RiotApiEnum::MATCH_COUNT_RETRIEVE->value,$startTime);
       return $callApiRiot;
   }

    /**
     * @param string $matchId
     * @return MatchDto|null
     * @throws GeneralException
     * @throws RequestException
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     */
   public function getDataMatchById(string $matchId): ?MatchDto
   {
       $callApiRiot = $this->riotApi->riotApiInit()->getMatch($matchId);
       return $callApiRiot;
   }
}
