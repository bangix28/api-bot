<?php

namespace App\Application\Player\LoginWithDiscord;

final readonly class LoginWithDiscordCommand
{
    public function __construct(
        /** Code renvoyé par Discord au front après autorisation. */
        public string $code,
    ) {
    }
}
