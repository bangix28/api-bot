<?php

namespace App\Domain\Player;

interface DiscordOAuthClientInterface
{
    /**
     * Échange le code renvoyé par Discord après autorisation contre l'identité
     * du joueur.
     *
     * @throws DiscordLoginFailedException code refusé, expiré ou Discord injoignable
     */
    public function identityFromCode(string $code): DiscordIdentity;
}
