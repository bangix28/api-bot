<?php

namespace App\Services\RiotApiServices;

    use App\Controller\RiotApi;
    use App\Controller\ValidationController;

    class RiotApiScore
    {
        public function __construct(private ValidationController $validationController,private RiotApi $riotApi)
        {}
        public function aramRankedScore($summonerInformations, $queueType)
        {
            $listOfGames = $this->validationController->getMatchsInformationsById($summonerInformations->puuid,$queueType);
            $score = 0;
            foreach ($listOfGames as $game)
            {
                $gameData = $this->riotApi->riotApiInit()->getMatch($game);
            }
        }

    }
