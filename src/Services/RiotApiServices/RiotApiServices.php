<?php

namespace App\Services\RiotApiServices;

use App\Controller\ValidationController;
use App\Entity\RiotAccount;
use App\Repository\RiotAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;
use Symfony\Component\Validator\Constraints\DateTime;

class RiotApiServices
{
    public function __construct(private ValidationController $validationController,private RiotAccountRepository $riotAccountRepository,private EntityManagerInterface $entityManager,private ScoreServices $scoreServices)
    {


    }
    public function riotAccountFill(RiotAccount $riotAccount)
    {
        $response = $this->getRankedInformations($riotAccount->getRiotId());


        if ($response->status && !empty($response->data)) {
            $rankedSoloSummonerInfo = $response->data;
            $score = $this->scoreServices->getScoreSummoner($rankedSoloSummonerInfo);

            $riotAccount->setSummonerRankedSoloLeaguePoints($rankedSoloSummonerInfo->leaguePoints)
                ->setSummonerRankedSoloLosses($rankedSoloSummonerInfo->losses)
                ->setSummonerRankedSoloRank($rankedSoloSummonerInfo->rank)
                ->setSummonerRankedSoloTier($rankedSoloSummonerInfo->tier)
                ->setSummonerRankedSoloWins($rankedSoloSummonerInfo->wins)
                ->setScore($score)
                ->setLastUpdate(new \DateTime('now'));

        }else{
            $riotAccount->setSummonerRankedSoloLeaguePoints(0)
                ->setSummonerRankedSoloRank('non classée')
                ->setSummonerRankedSoloTier(null)
                ->setScore(0)
                ->setSummonerRankedSoloWins(null)
                ->setSummonerRankedSoloLosses(null)
                ->setLastUpdate(new \DateTime('now'));
        }
        $this->entityManager->flush();
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
            return (object)array('status' => 'false');
        } catch (Exception) {
            return array('status' => 'false', 'error');
        }
    }

    /**
     * @throws \Exception
     */
    public function getListRanked(): array
    {
        $listeAccount = $this->riotAccountRepository->findAll();
        foreach ($listeAccount as $account) {
            $this->riotAccountFill($account);
        }
        return ['status' => true, 'data' => $listeAccount];
    }
}
