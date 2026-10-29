<?php

namespace App\Application\Player\RefreshSession;

use App\Application\Player\IssuedSession;
use App\Application\Player\OpenSession\OpenSessionHandler;
use App\Domain\Player\InvalidRefreshTokenException;
use App\Domain\Player\PlayerSessionRepositoryInterface;
use App\Domain\Shared\ClockInterface;

/**
 * Échange un jeton de rafraîchissement contre une session neuve : l'ancienne
 * est retirée (PlayerSession::retire), la nouvelle repart pour la durée complète.
 */
final readonly class RefreshSessionHandler
{
    public function __construct(
        private PlayerSessionRepositoryInterface $sessions,
        private OpenSessionHandler $openSession,
        private ClockInterface $clock,
    ) {
    }

    public function handle(string $plainRefreshToken): IssuedSession
    {
        $now = $this->clock->now();
        $session = $this->sessions->findByToken($plainRefreshToken);

        if ($session === null || $session->isExpiredAt($now)) {
            throw new InvalidRefreshTokenException('Session expirée : reconnecte-toi avec Discord.');
        }

        $this->sessions->save($session->retire($now));

        return $this->openSession->handle($session->player);
    }
}
