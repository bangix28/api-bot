<?php

namespace App\Tests\Domain\Agent;

use App\Domain\Agent\SecretGeneratorInterface;
use App\Domain\Agent\UserCode;

/** Secrets prévisibles : numérotés, et codes d'appairage rejoués dans l'ordre donné. */
final class ScriptedSecretGenerator implements SecretGeneratorInterface
{
    private int $deviceCodes = 0;
    private int $agentTokens = 0;

    /** @param string[] $userCodes codes servis dans l'ordre ; le dernier est répété */
    public function __construct(private array $userCodes = ['K7QF92PZ'])
    {
    }

    public function deviceCode(): string
    {
        return 'dev_' . ++$this->deviceCodes;
    }

    public function userCode(): UserCode
    {
        $next = count($this->userCodes) > 1 ? array_shift($this->userCodes) : $this->userCodes[0];

        return new UserCode($next);
    }

    public function agentToken(): string
    {
        return 'agt_' . ++$this->agentTokens;
    }
}
