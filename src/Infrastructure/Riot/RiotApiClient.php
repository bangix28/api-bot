<?php

namespace App\Infrastructure\Riot;

use RiotAPI\Base\Definitions\Region;
use RiotAPI\LeagueAPI\LeagueAPI;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class RiotApiClient
{
    public function __construct(
        #[Autowire('%app.riot.api.token%')]
        private string $riotApiToken,
    ) {}

    public function riotApiInit(): LeagueAPI
    {
        return new LeagueAPI([
            LeagueAPI::SET_KEY => $this->riotApiToken,
            LeagueAPI::SET_REGION => Region::EUROPE_WEST,
            LeagueAPI::SET_VERIFY_SSL => false,
            LeagueAPI::SET_DATADRAGON_INIT => true,
            LeagueAPI::SET_INTERIM => true,
            LeagueAPI::SET_CACHE_RATELIMIT => true,
            LeagueAPI::SET_CACHE_CALLS => true,
        ]);
    }

}
