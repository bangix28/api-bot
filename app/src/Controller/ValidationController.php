<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ValidationController
{
   public function __construct(private RiotApi $riotApi)
   {}
   public function getRiotAccountBySummoner(string $summonerName)
   {
       $callApiRiot = $this->riotApi->riotApiInit()->getSummonerByName('shoteur');
       return $callApiRiot;
   }

   public function getRankedsInformationsById($summonerId)
   {
       $callApiRiot = $this->riotApi->riotApiInit()->getLeagueEntriesForSummoner($summonerId);
       return $callApiRiot;
   }
}
