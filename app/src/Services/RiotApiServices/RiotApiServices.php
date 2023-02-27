<?php

namespace App\Services\RiotApiServices;

use App\Controller\ValidationController;
use App\Entity\RiotAccount;
use Symfony\Component\Validator\Constraints\DateTime;

class RiotApiServices
{
    public function __construct(private ValidationController $validationController)
    {}
    public function riotAccountFill(RiotAccount $riotAccount,)
    {
        $summonerInformations = $this->validationController->getRiotAccountBySummoner('shoteur');
        $rankedSoloSummonerInfo = $this->getRankedInformations($summonerInformations->id)->data;
        $riotAccount->setRiotId($summonerInformations->accountId)
            ->setPuuid($summonerInformations->puuid)
            ->setScore(0)
            ->setSummonerLevel($summonerInformations->summonerLevel)
            ->setSummonerName($summonerInformations->name)
            ->setSummonerRankedSoloLeaguePoints($rankedSoloSummonerInfo->leaguePoints)
            ->setSummonerRankedSoloLosses($rankedSoloSummonerInfo->losses)
            ->setSummonerRankedSoloRank($rankedSoloSummonerInfo->rank)
            ->setLastUpdate(new \DateTime('now'));
       return $riotAccount;
    }

    public function getRankedInformations($summonerId)
    {
        try {
            $rankedSummonerInformations = $this->validationController->getRankedsInformationsById($summonerId);
            foreach ($rankedSummonerInformations as $rankedinfo) {
                if ($rankedinfo->queueType === "RANKED_SOLO_5x5") {
                    return (object)array('status' => 'true', 'data' => $rankedinfo);
                }
            }
        } catch (Exception) {
            return array('status' => 'false', 'error');
        }
    }
}
