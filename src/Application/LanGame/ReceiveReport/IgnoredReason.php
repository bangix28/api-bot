<?php

namespace App\Application\LanGame\ReceiveReport;

/** Relevé valide mais sans objet : l'agent cesse d'envoyer pour cette partie (contrat, § 3). */
enum IgnoredReason: string
{
    case LAN_NOT_STARTED = 'lan_not_started';
    case LAN_FINISHED = 'lan_finished';
    case GAME_MODE_NOT_ACCEPTED = 'game_mode_not_accepted';
    case NO_PARTICIPANT_IN_GAME = 'no_participant_in_game';
    case JOINED_TOO_LATE = 'joined_too_late';
    case STALE_SEQUENCE = 'stale_sequence';
}
