<?php

namespace App\Domain\LanGame\Event;

/**
 * Un événement du journal de la partie (Live Client Data API, `events`).
 * Les joueurs y sont nommés par leur seul pseudo, sans le tag (ADR-0005) :
 * GameSnapshot les résout en Riot ID complet.
 */
interface ObservedEvent
{
}
