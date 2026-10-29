<?php

namespace App\Domain\Player;

/** Port des jetons de rafraîchissement : imprévisibles en production, scriptés en test. */
interface SessionTokenGeneratorInterface
{
    /** Jeton de rafraîchissement (préfixe PlayerSession::PREFIX). */
    public function refreshToken(): string;
}
