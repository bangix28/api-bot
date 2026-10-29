<?php

namespace App\Application\Player\OpenSession;

use App\Application\Player\IssuedSession;
use App\Domain\Agent\SecretDigest;
use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\PlayerSession;
use App\Domain\Player\PlayerSessionRepositoryInterface;
use App\Domain\Player\SessionTokenGeneratorInterface;
use App\Domain\Shared\ClockInterface;

/** Ouvre la session d'un joueur qui vient de se connecter avec Discord. */
final readonly class OpenSessionHandler
{
    public function __construct(
        private PlayerSessionRepositoryInterface $sessions,
        private SessionTokenGeneratorInterface $tokens,
        private ClockInterface $clock,
    ) {
    }

    public function handle(DiscordIdentity $player): IssuedSession
    {
        $plainToken = $this->tokens->refreshToken();
        $session = PlayerSession::open(SecretDigest::of($plainToken), $player, $this->clock->now());
        $this->sessions->save($session);

        return new IssuedSession($plainToken, $session);
    }
}
