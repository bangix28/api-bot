<?php

namespace App\Application\Player\LoginWithDiscord;

use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\DiscordOAuthClientInterface;

/**
 * Connecte un joueur avec Discord. Rien n'est écrit en base : un joueur est
 * une identité Discord et les comptes Riot qui la portent, liés ensuite.
 * Le jeton de session est émis par l'adaptateur HTTP à partir de l'identité.
 */
final readonly class LoginWithDiscordHandler
{
    public function __construct(private DiscordOAuthClientInterface $discord)
    {
    }

    public function handle(LoginWithDiscordCommand $command): DiscordIdentity
    {
        return $this->discord->identityFromCode($command->code);
    }
}
