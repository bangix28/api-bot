<?php

namespace App\Domain\Agent;

/**
 * Réglages envoyés à l'agent par la session (contrat, § 2) : fixés par le
 * serveur pour pouvoir changer sans redistribuer l'agent.
 */
final class AgentSettings
{
    /** gameData.gameMode de l'ARAM du chaos (vérifié sur une vraie partie, ADR-0005). */
    public const array ACCEPTED_GAME_MODES = ['KIWI'];
    public const int REPORT_INTERVAL_SECONDS = 15;
}
