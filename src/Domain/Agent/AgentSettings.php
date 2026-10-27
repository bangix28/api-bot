<?php

namespace App\Domain\Agent;

/**
 * Réglages envoyés à l'agent par la session (contrat, § 2) : fixés par le
 * serveur pour pouvoir changer sans redistribuer l'agent. Les files acceptées
 * sont propres à chaque LAN (LanSummary::$acceptedQueues).
 */
final class AgentSettings
{
    public const int REPORT_INTERVAL_SECONDS = 15;
}
