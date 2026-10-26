<?php

namespace App\Tests\Domain\Player;

use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\DiscordLoginFailedException;
use App\Domain\Player\DiscordOAuthClientInterface;

/** Accepte un seul code, connu d'avance ; tout autre code est refusé comme le ferait Discord. */
final readonly class FakeDiscordOAuthClient implements DiscordOAuthClientInterface
{
    public function __construct(
        private string $validCode,
        private DiscordIdentity $identity,
    ) {
    }

    public function identityFromCode(string $code): DiscordIdentity
    {
        if ($code !== $this->validCode) {
            throw new DiscordLoginFailedException('Code refusé.');
        }

        return $this->identity;
    }
}
