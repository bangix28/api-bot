<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use RiotAPI\Base\Definitions\Region;
use RiotAPI\LeagueAPI\LeagueAPI;

class RiotApi extends AbstractController
{
    public function riotApiInit()
    {
        $riotApiToken = $this->getParameter('app.riot.api.token');
        $test = $riotApiToken;
        return new LeagueAPI([
            LeagueAPI::SET_KEY => $riotApiToken,
            LeagueAPI::SET_REGION => Region::EUROPE_WEST,
            LeagueAPI::SET_VERIFY_SSL => false,
            LeagueAPI::SET_DATADRAGON_INIT => true,
            LeagueAPI::SET_INTERIM => true,
            LeagueAPI::SET_CACHE_RATELIMIT => true,
            LeagueAPI::SET_CACHE_CALLS => true,
        ]);
    }

}
