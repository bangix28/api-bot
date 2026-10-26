<?php

namespace App\Tests\Application\Player\GetProfile;

use App\Application\Player\GetProfile\GetProfileHandler;
use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\TrackedRiotAccount;
use App\Tests\Domain\Player\InMemoryTrackedRiotAccountRepository;
use PHPUnit\Framework\TestCase;

class GetProfileHandlerTest extends TestCase
{
    public function testLeProfilListeLesSeulsComptesDuJoueur(): void
    {
        $accounts = (new InMemoryTrackedRiotAccountRepository())
            ->add(new TrackedRiotAccount('Principal#EUW', 'puuid-1', '123'))
            ->add(new TrackedRiotAccount('Smurf#EUW', 'puuid-2', '123'))
            ->add(new TrackedRiotAccount('Ami#EUW', 'puuid-3', '999'))
            ->add(new TrackedRiotAccount('Libre#EUW', 'puuid-4', null));

        $profile = (new GetProfileHandler($accounts))->handle(new DiscordIdentity('123', 'pseudo'));

        $this->assertSame('123', $profile->discordId);
        $this->assertSame('pseudo', $profile->discordUsername);
        $this->assertSame(['Principal#EUW', 'Smurf#EUW'], $profile->riotIds);
    }

    public function testUnJoueurFraichementConnecteNAAucunCompte(): void
    {
        $profile = (new GetProfileHandler(new InMemoryTrackedRiotAccountRepository()))->handle(new DiscordIdentity('123', 'pseudo'));

        $this->assertSame([], $profile->riotIds);
    }
}
