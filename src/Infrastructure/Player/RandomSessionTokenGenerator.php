<?php

namespace App\Infrastructure\Player;

use App\Domain\Player\PlayerSession;
use App\Domain\Player\SessionTokenGeneratorInterface;
use Random\Engine\Secure;
use Random\Randomizer;

/** Jetons tirés par le générateur cryptographique de PHP. */
final readonly class RandomSessionTokenGenerator implements SessionTokenGeneratorInterface
{
    private const int SECRET_BYTES = 32;

    public function refreshToken(): string
    {
        $bytes = (new Randomizer(new Secure()))->getBytes(self::SECRET_BYTES);

        return PlayerSession::PREFIX . rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
