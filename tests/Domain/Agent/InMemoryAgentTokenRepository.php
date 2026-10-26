<?php

namespace App\Tests\Domain\Agent;

use App\Domain\Agent\AgentToken;
use App\Domain\Agent\AgentTokenRepositoryInterface;
use App\Domain\Agent\SecretDigest;

final class InMemoryAgentTokenRepository implements AgentTokenRepositoryInterface
{
    /** @var array<string, AgentToken> jetons actifs, indexés par empreinte */
    public array $active = [];

    /** @var string[] empreintes des jetons révoqués */
    public array $revokedDigests = [];

    public function saveReplacingPrevious(AgentToken $token): void
    {
        foreach ($this->active as $digest => $previous) {
            if ($previous->player->userId === $token->player->userId && $previous->lanId === $token->lanId) {
                unset($this->active[$digest]);
                $this->revokedDigests[] = $digest;
            }
        }

        $this->active[$token->digest] = $token;
    }

    public function findActive(string $plainToken): ?AgentToken
    {
        return $this->active[SecretDigest::of($plainToken)] ?? null;
    }
}
