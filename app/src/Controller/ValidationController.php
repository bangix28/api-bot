<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\RiotAccountExistException;
use App\Repository\RiotAccountRepository;
use App\Repository\UserRepository;

class ValidationController
{
   public function __construct(private RiotApi $riotApi,private UserRepository $userRepository,private RiotAccountRepository $riotAccount)
   {}
   public function getRiotAccountBySummoner(string $summonerName)
   {
       $callApiRiot = $this->riotApi->riotApiInit()->getSummonerByName('shoteur');
       return $callApiRiot;
   }
    public function getRankedsInformationsById($summonerId)
    {
        $callApiRiot = $this->riotApi->riotApiInit()->getLeagueEntriesForSummoner($summonerId);
            try {
                foreach ($callApiRiot as $rankedinfo) {
                    if ($rankedinfo->queueType === "RANKED_SOLO_5x5") {
                        return (object)array('status' => 'true', 'data' => $rankedinfo);
                    }
                }
            } catch (Exception) {
                return array('status' => 'false', 'error');
            }
    }
   public function getMatchsInformationsById($puuid,$queueType,$gameType = null,$beginScraps = null): array
   {
       $numbersGamesToget = 10;
       $callApiRiot = $this->riotApi->riotApiInit()->getMatchIdsByPUUID($puuid,$queueType,$gameType,$beginScraps,$numbersGamesToget);
       return $callApiRiot;
   }

    /**
     * @return void
     * @throws RiotAccountExistException
     * Vérifie si le compte discord est liée au bot.
     */
   public function verifyDiscordAccount(string $discordId): User
   {
       $user = $this->userRepository->findOneBy(array('discordId' => $discordId));
       if ($user === null)
       {
           throw new RiotAccountExistException(sprintf('Le compte discord "%s" n\'est pas lié au Bot', $discordId));
       }
       return $user;
   }

}
