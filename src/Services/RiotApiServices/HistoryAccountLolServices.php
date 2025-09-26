<?php

namespace App\Services\RiotApiServices;

use App\Controller\ValidationController;
use App\Entity\HistoryAccountLol;
use App\Entity\RiotAccount;
use App\Repository\DataChallengeRepository;
use App\Repository\HistoryAccountLolRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Expr\Array_;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;
use RiotAPI\LeagueAPI\Objects\MatchDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HistoryAccountLolServices extends AbstractController
{
    public function __construct(private ValidationController $validationController,private HistoryAccountLolRepository $historyAccountLolRepository,private DataChallengeRepository $dataChallengeRepository,private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getHistoryAccountLol(RiotAccount $riotAccount): void
    {
        if (!$riotAccount->getSummonerRankedSoloTier())
        {
            return ;
        }
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

    public function createHistoryAccountLol(MatchDto $dataMatchHistoryLol, RiotAccount $riotAccount): void
    {
        // Récupération des données essentielles
        $dataMatch = $dataMatchHistoryLol->getData();
        $gameInfo = $dataMatch['info'] ?? [];
        $listOfParticipants = $gameInfo['participants'] ?? [];
        // Validation des données nécessaires
        if (empty($gameInfo) || empty($listOfParticipants)) {
            throw new \InvalidArgumentException('Les données de la partie sont invalides ou incomplètes.');
        }

        // Extraction et traitement des données du joueur
        $dataSummoner = $this->getDataSummonerByListOfParicipants($riotAccount, $listOfParticipants);
        $clearedSummonerData = $this->clearChallengeByQueue($dataSummoner);

        // Transformation du timestamp en DateTime et converti le timestamp de l'API de millisecondes en secondes
        $dateTimeEndGame = (new \DateTime())->setTimestamp((int) ($gameInfo['gameEndTimestamp'] / 1000));
        $dateTimeDuration = (int)($gameInfo['gameDuration'] / 60);

        // Création et configuration de l'entité HistoryAccountLol
        $historyAccountLol = (new HistoryAccountLol())
            ->setRiotAccount($riotAccount)
            ->setIsWin($dataSummoner['win'] ?? false)
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setDateGameEnd($dateTimeEndGame)
            ->setChampionId($dataSummoner['championId'] ?? 0)
            ->setKillPlayer($dataSummoner['kills'] ?? 0)
            ->setAssistPlayer($dataSummoner['assists'] ?? 0)
            ->setDeathPlayer($dataSummoner['deaths'] ?? 0)
            ->setGameDuration($dateTimeDuration)
            ->setData($clearedSummonerData);

        // Persistance de l'entité
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