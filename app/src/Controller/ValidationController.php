<?php

namespace App\Controller;

use App\Entity\RiotAccount;
use App\Entity\User;
use App\Exception\RiotAccountExistException;
use App\Repository\RiotAccountRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception;
use Symfony\Component\Validator\Constraints\DateTime;

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
            throw new Exception('Je ne sais pas pourquoi tu as cette erreur, Contact Kenolane.');
    }
   public function getMatchsInformationsById(string $puuid,int $queueType,$gameType = null,$beginScraps = null,int $numberGamesToGet = 10,$maxDateGames = null): array
   {
       if (is_null($maxDateGames)){
           $maxDateGames = null;
       }else{
           $maxDateGames = strtotime($maxDateGames->format('Y-m-d H:i:s'));
       }
       $callApiRiot = $this->riotApi->riotApiInit()->getMatchIdsByPUUID($puuid,$queueType,$gameType,$beginScraps,$numberGamesToGet,$maxDateGames);
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
