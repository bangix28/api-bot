<?php

namespace App\Services\RiotApiServices;

use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Entity\RiotAccount;
use App\Infrastructure\Riot\RiotApiGateway;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RiotApiServices
{
    public function __construct(private readonly RiotApiGateway             $validationController,
                                private readonly EntityManagerInterface     $entityManager,
                                private readonly LoggerInterface            $riotLogger = new NullLogger(),
    )
    {
    }
    public function riotAccountFill(RiotAccount $riotAccount): RiotAccount
    {
        $summonerDetails = $this->validationController->getSummonerAcountsDetails($riotAccount->getPuuid());
        $response = $this->getRankedInformations($riotAccount->getPuuid());

        if ($response->status && !empty($response->data)) {
            $rankedSoloSummonerInfo = $response->data;
            $score = RankedTier::fromString($rankedSoloSummonerInfo->tier)->getScore()
                + RankedRank::fromString($rankedSoloSummonerInfo->rank)->getScore()
                + (int) $rankedSoloSummonerInfo->leaguePoints;

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
            // Représentation normalisée d'un compte non classé (cf. migration
            // Version20260628111804 et RankedTier/RankedRank::UNRANKED). Écrire
            // 'non classée'/null ici défait la normalisation et fait planter
            // RankedTier::fromString(null) côté lecture hexagonale.
            $riotAccount->setSummonerRankedSoloLeaguePoints(0)
                ->setSummonerRankedSoloRank(RankedRank::UNRANKED->value)
                ->setSummonerRankedSoloTier(RankedTier::UNRANKED->value)
                ->setScore(0)
                ->setSummonerRankedSoloWins(0)
                ->setSummonerRankedSoloLosses(0)
                ->setLogoId($summonerDetails->profileIconId ?? 0)
                ->setSummonerLevel($summonerDetails->summonerLevel ?? 0)
                ->setLastUpdate(new \DateTime('now'));
        }
        $this->entityManager->flush();
        return $riotAccount;
    }

    public function getRankedInformations($summonerId): object
    {
        try {
            $rankedSummonerInformations = $this->validationController->getRankedsInformationsById($summonerId);
            foreach ($rankedSummonerInformations as $rankedinfo) {
                if ($rankedinfo->queueType === "RANKED_SOLO_5x5") {
                    return (object)array('status' => 'true', 'data' => $rankedinfo);
                }
            }
            return (object)array('status' => 'false');
        } catch (\Exception $e) {
            // Conséquence métier : le compte sera traité comme « non classé ».
            // Le détail technique de l'appel est déjà logué par RiotApiGateway.
            $this->riotLogger->error('Échec de récupération du ranked', [
                'puuid' => $summonerId,
                'exception' => $e,
            ]);

            return (object)array('status' => 'false');
        }
    }

}
