<?php

namespace App\Domain\LanChallenge;

enum FeedEntryKind: string
{
    /** Une manche réglée : carte, verdict, points. */
    case ROUND = 'round';
    /** Un sabotage visible (Pickpocket), sans le nom du saboteur. */
    case SABOTAGE = 'sabotage';
}
