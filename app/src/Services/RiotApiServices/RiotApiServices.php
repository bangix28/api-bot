<?php

namespace App\Services\RiotApiServices;

use App\Controller\ValidationController;
use App\Entity\RiotAccount;
use DateTime;

class RiotApiServices
{
    public function __construct(private ValidationController $validationController, private RiotApiScore $riotApiScore)
    {
    }

    public function riotAccountFill(RiotAccount $riotAccount, $summonerName)
    {
        if (!$riotAccount->getSummonerId()) {
            $summonerInformations = $this->validationController
                ->getRiotAccountBySummoner($summonerName);

            $rankedSoloSummonerInfo = $this->validationController
                ->getRankedsInformationsById($summonerInformations->id)
                ->data;

            $scoreToAdd = $this->riotApiScore->aramRankedScore(
                $summonerInformations->puuid,
                440,
                $riotAccount
            );

            $riotAccount
                ->setPuuid($summonerInformations->puuid)
                ->setRiotId($summonerInformations->accountId)
                ->setSummonerId($summonerInformations->id)
                ->setSummonerLevel($summonerInformations->summonerLevel)
                ->setSummonerName($summonerInformations->name);
        } else {
            $rankedSoloSummonerInfo = $this->validationController
                ->getRankedsInformationsById($riotAccount->getSummonerId())
                ->data;

            $scoreToAdd = $this->riotApiScore->aramRankedScore(
                 $riotAccount->getPuuid(),
                440,
                $riotAccount
            );
        }

        $riotAccount
            ->setScore($riotAccount->getScore() + $scoreToAdd)
            ->setSummonerRankedSoloLeaguePoints($rankedSoloSummonerInfo->leaguePoints)
            ->setSummonerRankedSoloTier($rankedSoloSummonerInfo->tier)
            ->setSummonerRankedSoloLosses($rankedSoloSummonerInfo->losses)
            ->setSummonerRankedSoloWins($rankedSoloSummonerInfo->wins)
            ->setSummonerRankedSoloRank($rankedSoloSummonerInfo->rank)
            ->setLastUpdate(new DateTime('now'));

        return $riotAccount;
    }

}
