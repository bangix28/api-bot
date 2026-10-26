<?php

namespace App\Domain\Agent;

/**
 * Empreinte d'un secret (deviceCode, jeton d'agent) : c'est elle, et jamais le
 * secret, que le serveur conserve. Un vol de la base ne donne aucun jeton
 * utilisable.
 */
final class SecretDigest
{
    public static function of(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
