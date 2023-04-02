<?php

namespace App\Services\RiotApiServices;

use App\Controller\ValidationController;
use App\Entity\RiotAccount;
use Symfony\Component\Validator\Constraints\DateTime;

class RiotApiServices
{
    public function __construct(private ValidationController $validationController , private RiotApiScore $riotApiScore)
    {}
    public function riotAccountFill(RiotAccount $riotAccount,$summonerName)
    {
        $summonerInformations = $this->validationController->getRiotAccountBySummoner($summonerName);
        $rankedSoloSummonerInfo = $this->validationController->getRankedsInformationsById($summonerInformations->id)->data;
        $scoreToAdd = $this->riotApiScore->aramRankedScore($summonerInformations,440,$riotAccount);
        $newScoreRiotAccount = $riotAccount->getScore() + $scoreToAdd;
        $riotAccount->setRiotId($summonerInformations->accountId)
            ->setPuuid($summonerInformations->puuid)
            ->setScore($newScoreRiotAccount)
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
