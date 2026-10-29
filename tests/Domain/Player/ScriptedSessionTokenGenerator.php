<?php

namespace App\Tests\Domain\Player;

use App\Domain\Player\SessionTokenGeneratorInterface;

/** Jetons prévisibles : numérotés dans l'ordre d'émission. */
final class ScriptedSessionTokenGenerator implements SessionTokenGeneratorInterface
{
    private int $issued = 0;

    public function refreshToken(): string
    {
        return 'rft_' . ++$this->issued;
    }
}
