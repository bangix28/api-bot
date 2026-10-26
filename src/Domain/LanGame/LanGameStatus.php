<?php

namespace App\Domain\LanGame;

enum LanGameStatus: string
{
    /** Partie en cours : les cartes engagées attendent le relevé final. */
    case IN_PROGRESS = 'in_progress';
    /** Premier relevé reçu trop tard : la partie n'engage aucune carte. */
    case JOINED_TOO_LATE = 'joined_too_late';
    /** Relevé final reçu, cartes réglées. */
    case SETTLED = 'settled';
    /** Plus de nouvelles : réglée en verdict inconnu. */
    case ABANDONED = 'abandoned';
}
