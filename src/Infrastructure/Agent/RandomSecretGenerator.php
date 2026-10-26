<?php

namespace App\Infrastructure\Agent;

use App\Domain\Agent\AgentToken;
use App\Domain\Agent\SecretGeneratorInterface;
use App\Domain\Agent\UserCode;
use Random\Engine\Secure;
use Random\Randomizer;

/** Secrets tirés par le générateur cryptographique de PHP. */
final readonly class RandomSecretGenerator implements SecretGeneratorInterface
{
    private const int SECRET_BYTES = 32;

    private Randomizer $randomizer;

    public function __construct()
    {
        $this->randomizer = new Randomizer(new Secure());
    }

    public function deviceCode(): string
    {
        return 'dev_' . $this->urlSafeSecret();
    }

    public function userCode(): UserCode
    {
        return new UserCode($this->randomizer->getBytesFromString(UserCode::ALPHABET, UserCode::LENGTH));
    }

    public function agentToken(): string
    {
        return AgentToken::PREFIX . $this->urlSafeSecret();
    }

    private function urlSafeSecret(): string
    {
        return rtrim(strtr(base64_encode($this->randomizer->getBytes(self::SECRET_BYTES)), '+/', '-_'), '=');
    }
}
