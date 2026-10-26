<?php

namespace App\Domain\Agent;

/** Port des secrets de l'appairage : imprévisibles en production, scriptés en test. */
interface SecretGeneratorInterface
{
    /** Secret de l'agent avant le jeton (préfixe « dev_ »). */
    public function deviceCode(): string;

    public function userCode(): UserCode;

    /** Jeton d'agent (préfixe AgentToken::PREFIX). */
    public function agentToken(): string;
}
