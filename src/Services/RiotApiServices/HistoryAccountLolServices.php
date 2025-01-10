<?php

namespace App\Services\RiotApiServices;

use App\Controller\ValidationController;
use App\Entity\HistoryAccountLol;
use App\Entity\RiotAccount;
use App\Repository\DataChallengeRepository;
use App\Repository\HistoryAccountLolRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Expr\Array_;
use RiotAPI\LeagueAPI\Objects\MatchDto;

class HistoryAccountLolServices
{
    public function __construct(private ValidationController $validationController,private HistoryAccountLolRepository $historyAccountLolRepository,private DataChallengeRepository $dataChallengeRepository,private EntityManagerInterface $entityManager)
    {
    }

    public function getHistoryAccountLol(RiotAccount $riotAccount)
    {
        if ($riotAccount->getLastUpdate()){
            $lastUpdateTimeStamp = $riotAccount->getLastUpdate()->getTimestamp();
        }else{
            $lastUpdateTimeStamp = null;
        }
        $getListIdMatchHistoryLol = $this->validationController->getListIdMatchHistoryLol($riotAccount->getPuuid(),$lastUpdateTimeStamp);
        foreach ($getListIdMatchHistoryLol as $matchHistory) {
            $dataMatchHistory = $this->validationController->getDataMatchById($matchHistory);
            $this->createHistoryAccountLol($dataMatchHistory,$riotAccount);
        }
    }

    public function createHistoryAccountLol(MatchDto $dataMatchHistoryLol,RiotAccount $riotAccount)
    {
        $listOfParticipants = $dataMatchHistoryLol->getData()['info']['participants'];
        $dataSummoner = $this->getDataSummonerByListOfParicipants($riotAccount,$listOfParticipants);
        $clearedSummonerData = $this->clearChallengeByQueue($dataSummoner);


        $historyAccountLol = new HistoryAccountLol();
        $historyAccountLol->setRiotAccount($riotAccount);
        $historyAccountLol->setUpdatedAt(new \DateTimeImmutable());
        $historyAccountLol->setData($clearedSummonerData);

        $this->entityManager->persist($historyAccountLol);
        $this->entityManager->flush();
    }

    public function getDataSummonerByListOfParicipants(RiotAccount $riotAccount,array $listOfParticipants)
    {
        $puuidSummonerTarget = $riotAccount->getPuuid();
        foreach ($listOfParticipants as $participant)
        {
            if ($participant['puuid'] == $puuidSummonerTarget)
            {
                $dataSummoner = $participant;
            }
        }
        return $dataSummoner;
    }

    /**
     * @param array $dataSummoner
     * @return void
     * Récupere la liste des challenge a garder et ensuite clear le reste
     */
    public function clearChallengeByQueue(array $dataSummoner)
    {
        $listOfChallenges = $this->dataChallengeRepository->findChallengeByQueue(420);
        $challengesSummoner = $dataSummoner['challenges'];
        $challengesSummoner = array_filter($challengesSummoner, function($key) use ($listOfChallenges) {
            return in_array($key, $listOfChallenges);
        }, ARRAY_FILTER_USE_KEY);

        // Remplacer les challenges dans dataSummoner
        $dataSummoner['challenges'] = $challengesSummoner;

        return $dataSummoner;
    }

}