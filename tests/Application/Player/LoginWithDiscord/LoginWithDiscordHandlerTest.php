<?php

namespace App\Tests\Application\Player\LoginWithDiscord;

use App\Application\Player\LoginWithDiscord\LoginWithDiscordCommand;
use App\Application\Player\LoginWithDiscord\LoginWithDiscordHandler;
use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\DiscordLoginFailedException;
use App\Tests\Domain\Player\FakeDiscordOAuthClient;
use PHPUnit\Framework\TestCase;

class LoginWithDiscordHandlerTest extends TestCase
{
    private function handler(): LoginWithDiscordHandler
    {
        return new LoginWithDiscordHandler(new FakeDiscordOAuthClient('code-valide', new DiscordIdentity('123', 'pseudo')));
    }

    public function testUnCodeValideDonneLIdentiteDuJoueur(): void
    {
        $identity = $this->handler()->handle(new LoginWithDiscordCommand('code-valide'));

        $this->assertSame('123', $identity->userId);
        $this->assertSame('pseudo', $identity->username);
    }

    public function testUnCodeRefuseParDiscordEchoue(): void
    {
        $this->expectException(DiscordLoginFailedException::class);

        $this->handler()->handle(new LoginWithDiscordCommand('code-expiré'));
    }
}
