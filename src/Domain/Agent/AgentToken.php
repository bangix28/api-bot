<?php

namespace App\Domain\Agent;

use App\Domain\Player\DiscordIdentity;

/**
 * Jeton d'un agent appairé : il prouve que les relevés viennent d'un joueur
 * donné, pour une LAN donnée. Il expire à la fin de la LAN, avec une heure de
 * grâce réservée au relevé final d'une partie commencée avant (contrat, § 1).
 *
 * Seule l'empreinte du jeton est connue ici : la valeur en clair n'existe qu'au
 * moment de son émission, pour être remise à l'agent.
 */
final readonly class AgentToken
{
    public const string PREFIX = 'agt_';
    public const int FINAL_REPORT_GRACE_SECONDS = 3600;

    public function __construct(
        public string $digest,
        public DiscordIdentity $player,
        public int $lanId,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    public function isExpiredAt(\DateTimeImmutable $instant): bool
    {
        return $instant > $this->expiresAt;
    }

    /** Encore accepté pour un relevé final, une heure après la fin de la LAN. */
    public function acceptsFinalReportAt(\DateTimeImmutable $instant): bool
    {
        return $instant <= $this->expiresAt->modify(sprintf('+%d seconds', self::FINAL_REPORT_GRACE_SECONDS));
    }
}
