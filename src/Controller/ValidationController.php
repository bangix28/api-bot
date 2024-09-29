<?php

namespace App\Controller;

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
}
