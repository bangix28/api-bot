<?php

namespace App\Services\RiotApiServices;

use App\Controller\ValidationController;
use App\Entity\RiotAccount;
use Symfony\Component\Validator\Constraints\DateTime;

class RiotApiServices
{
    public function __construct(private ValidationController $validationController , private RiotApiScore $riotApiScore)
    {}
    public function riotAccountFill(RiotAccount $riotAccount)
    {
        $summonerInformations = $this->validationController->getRiotAccountBySummoner('shoteur');
        $listOfGames = $this->validationController->getMatchsInformationsById($summonerInformations->puuid,440);
        $rankedSoloSummonerInfo = $this->validationController->getRankedsInformationsById($summonerInformations->id)->data;
        dump($listOfGames);
        $riotAccount->setRiotId($summonerInformations->accountId)
            ->setPuuid($summonerInformations->puuid)
            ->setScore(0)
            ->setSummonerLevel($summonerInformations->summonerLevel)
            ->setSummonerName($summonerInformations->name)
            ->setSummonerRankedSoloLeaguePoints($rankedSoloSummonerInfo->leaguePoints)
            ->setSummonerRankedSoloTier($rankedSoloSummonerInfo->tier)
            ->setSummonerRankedSoloLosses($rankedSoloSummonerInfo->losses)
            ->setSummonerRankedSoloWins($rankedSoloSummonerInfo->wins)
            ->setSummonerRankedSoloRank($rankedSoloSummonerInfo->rank)
            ->setLastUpdate(new \DateTime('now'));
       return $riotAccount;
    }

    public function getGamesInformations($listOfGames)
    {
        try {
            foreach ($listOfGames as $game) {
                return (object)array('status' => 'true', 'data' => $game);
            }
        } catch (Exception) {
            return array('status' => 'false', 'error');
        }
    }
}
