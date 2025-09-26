<?php

namespace App\Services\RiotApiServices;

use App\Controller\ValidationController;
use App\Entity\RiotAccount;
use App\Entity\SummonerEloDaily;
use App\Repository\RiotAccountRepository;
use App\Repository\SummonerEloDailyRepository;
use Doctrine\ORM\EntityManagerInterface;

class RiotApiServices
{
    public function __construct(private readonly ValidationController       $validationController,
                                private readonly RiotAccountRepository      $riotAccountRepository,
                                private readonly SummonerEloDailyRepository $summonerEloDailyRepository,
                                private readonly EntityManagerInterface     $entityManager,
                                private readonly ScoreServices              $scoreServices,
    )
    {
    }
    public function riotAccountFill(RiotAccount $riotAccount): RiotAccount
    {
        $summonerDetails = $this->validationController->getSummonerAcountsDetails($riotAccount->getPuuid());
        $response = $this->getRankedInformations($riotAccount->getPuuid());

        if ($response->status && !empty($response->data)) {
            $rankedSoloSummonerInfo = $response->data;
            $score = $this->scoreServices->getScoreSummoner($rankedSoloSummonerInfo);

            $riotAccount->setSummonerRankedSoloLeaguePoints($rankedSoloSummonerInfo->leaguePoints)
                ->setSummonerRankedSoloLosses($rankedSoloSummonerInfo->losses)
                ->setSummonerRankedSoloRank($rankedSoloSummonerInfo->rank)
                ->setSummonerRankedSoloTier($rankedSoloSummonerInfo->tier)
                ->setSummonerRankedSoloWins($rankedSoloSummonerInfo->wins)
                ->setScore($score)
                ->setLogoId($summonerDetails->profileIconId ?? 0)
                ->setSummonerLevel($summonerDetails->summonerLevel ?? 0)
                ->setLastUpdate(new \DateTime('now'));

        } else {
            $riotAccount->setSummonerRankedSoloLeaguePoints(0)
                ->setSummonerRankedSoloRank('non classée')
                ->setSummonerRankedSoloTier(null)
                ->setScore(0)
                ->setSummonerRankedSoloWins(null)
                ->setSummonerRankedSoloLosses(null)
                ->setLogoId($summonerDetails->profileIconId ?? 0)
                ->setSummonerLevel($summonerDetails->summonerLevel ?? 0)
                ->setLastUpdate(new \DateTime('now'));
        }
        $this->entityManager->flush();
        return $riotAccount;
    }

    /**
     * On vérifie qu'il n'y a pas d'entrée pour aujourd'hui et si pas d'entrée,
     * alors on ajoute une entrée avec la l'elo le plus récent.
     * @param RiotAccount $riotAccount
     * @return RiotAccount
     */
    public function getDailyElo(RiotAccount $riotAccount): RiotAccount
    {
        $listeAccount = $this->riotAccountRepository->findAll();
        $existing = $this->summonerEloDailyRepository
            ->findOneBy([
                'riotAccount' => $riotAccount,
                'dateScore' => new \DateTime('now'),
            ]);

        if ($existing) {
            return $riotAccount;
        }

        $response = $this->getRankedInformations($riotAccount->getPuuid());
        if ($response->status && !empty($response->data)) {
            $score = $this->scoreServices->getScoreSummoner($response->data);
            $dailyElo = new SummonerEloDaily();
            $dailyElo->setRiotAccount($riotAccount)
                ->setScore($score)
                ->setDateScore(new \DateTime('now'));

            $this->entityManager->persist($dailyElo);
            $this->entityManager->flush();

        }
        return $riotAccount;
    }

    public function getRankedInformations($summonerId): array|object
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

    public function getListAccount(): array
    {
        $listeAccount = $this->riotAccountRepository->findAll();
        foreach ($listeAccount as $account) {
            $this->getDailyElo($account);
        }

        return ['status' => true, 'data' => $listeAccount];
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
