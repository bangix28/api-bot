<?php

namespace App\Domain\Agent;

/** Réponse à l'agent qui interroge avec son deviceCode (contrat, § 1.3). */
enum DevicePollStatus: string
{
    /** Le joueur n'a pas encore saisi le code sur le front. */
    case PENDING = 'authorization_pending';
    /** L'agent interroge plus vite que l'intervalle annoncé. */
    case SLOW_DOWN = 'slow_down';
    /** Le code a expiré sans être approuvé. */
    case EXPIRED = 'expired_token';
    /** Approuvé : le jeton peut être émis, une seule fois. */
    case APPROVED = 'approved';
}
