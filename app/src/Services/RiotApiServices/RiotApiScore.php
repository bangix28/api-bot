<?php

namespace App\Services\RiotApiServices;

    use App\Controller\RiotApi;
    use App\Controller\ValidationController;
    use App\Entity\RiotAccount;
    use App\Exception\DiscordNotFoundException;
    use RiotAPI\Base\Exceptions\GeneralException;
    use RiotAPI\Base\Exceptions\RequestException;
    use RiotAPI\Base\Exceptions\ServerException;
    use RiotAPI\Base\Exceptions\ServerLimitException;
    use RiotAPI\Base\Exceptions\SettingsException;
    use RiotAPI\LeagueAPI\Objects\MatchDto;
    use RiotAPI\LeagueAPI\Objects\SummonerDto;

    class RiotApiScore
    {
        public function __construct(private ValidationController $validationController,private RiotApi $riotApi)
        {}

        /**
         * @throws ServerException
         * @throws ServerLimitException
         * @throws SettingsException
         * @throws RequestException
         * @throws GeneralException
         */
        public function aramRankedScore(SummonerDto $summonerInformations,int $queueType,RiotAccount $riotAccount)
        {
            $listOfGames = $this->validationController->getMatchsInformationsById($summonerInformations->puuid,$queueType,null,null,10,$riotAccount->getLastUpdate());
            $score = 0;
            foreach ($listOfGames as $game)
            {
                dump($score);
                $gameData = $this->riotApi->riotApiInit()->getMatch($game);
                $participantData = $this->getGameDataFromMatchesForSummoner($gameData->info->participants,$summonerInformations->puuid);
                $score += $participantData->kills * 1;
                $score += $participantData->assists * 0.5;
                $score -= $participantData->deaths * 0.5;
                $score += $participantData->turretKills * 0.3;
                $score -= $participantData->turretsLost * 0.2;
            }
            return $score;
        }

        public function getGameDataFromMatchesForSummoner($participants,$puuid)
        {
            foreach ($participants as $participantData)
            {
                if ($participantData->puuid === $puuid){
                    return $participantData;
                }
            }
            throw new DiscordNotFoundException(sprintf('Test'));
        }

    }
