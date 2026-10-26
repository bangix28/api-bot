<?php

namespace App\Tests\Domain\Player;

use App\Domain\Player\DiscordIdentity;
use PHPUnit\Framework\TestCase;

class DiscordIdentityTest extends TestCase
{
    public function testUneIdentiteValide(): void
    {
        $identity = new DiscordIdentity('80351110224678912', 'pseudo');

        $this->assertSame('80351110224678912', $identity->userId);
        $this->assertSame('pseudo', $identity->username);
    }

    public function testUnIdentifiantDiscordNeContientQueDesChiffres(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DiscordIdentity('discord:123', 'pseudo');
    }

    public function testUnNomDUtilisateurVideEstRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DiscordIdentity('123', '   ');
    }
}
