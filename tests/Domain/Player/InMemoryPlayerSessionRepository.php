<?php

namespace App\Tests\Domain\Player;

use App\Domain\Agent\SecretDigest;
use App\Domain\Player\PlayerSession;
use App\Domain\Player\PlayerSessionRepositoryInterface;

final class InMemoryPlayerSessionRepository implements PlayerSessionRepositoryInterface
{
    /** @var array<string, PlayerSession> indexées par empreinte */
    public array $sessions = [];

    public function save(PlayerSession $session): void
    {
        $this->sessions[$session->digest] = $session;
    }

    public function findByToken(string $plainToken): ?PlayerSession
    {
        return $this->sessions[SecretDigest::of($plainToken)] ?? null;
    }

    public function delete(PlayerSession $session): void
    {
        unset($this->sessions[$session->digest]);
    }
}
