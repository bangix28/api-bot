<?php

namespace App\Controller;

use App\Enum\RiotApiEnum;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ValidationController
{
   public function __construct(private RiotApi $riotApi)
   {}

    /**
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getRiotAccountBySummoner(string $summonerName)
   {
       $callApiRiot = $this->riotApi->riotApiInit()->getSummonerByName('shoteur');
       return $callApiRiot;
   }

    /**
     * @throws ServerLimitException
     * @throws ServerException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getRankedsInformationsById($summonerId)
   {
       $callApiRiot = $this->riotApi->riotApiInit()->getLeagueEntriesForSummoner($summonerId);
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
   public function getListIdMatchHistoryLol(string $puuid,string $startTime = null)
   {
       $callApiRiot = $this->riotApi->riotApiInit()->getMatchIdsByPUUID($puuid,RiotApiEnum::QUEUE_TYPE_RANKED_SOLO->value,null,RiotApiEnum::START_INDEX->value,RiotApiEnum::MATCH_COUNT_RETRIEVE->value,$startTime);
       return $callApiRiot;
   }

   public function getDataMatchById(string $matchId)
   {
       $callApiRiot = $this->riotApi->riotApiInit()->getMatch($matchId);
       return $callApiRiot;
   }
}
