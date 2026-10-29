<?php

namespace App\Application\Player\EndSession;

use App\Domain\Player\PlayerSessionRepositoryInterface;

/**
 * Déconnecte un joueur sur un appareil : sa session disparaît, son jeton de
 * rafraîchissement ne sert plus. Ses autres appareils restent connectés.
 *
 * Un jeton inconnu ne lève rien : se déconnecter deux fois, ou après
 * expiration, aboutit au même état.
 */
final readonly class EndSessionHandler
{
    public function __construct(private PlayerSessionRepositoryInterface $sessions)
    {
    }

    public function handle(string $plainRefreshToken): void
    {
        $session = $this->sessions->findByToken($plainRefreshToken);

        if ($session !== null) {
            $this->sessions->delete($session);
        }
    }
}
