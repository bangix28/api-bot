<?php

namespace App\Domain\Player;

/**
 * Session d'un joueur connecté par Discord, portée par un jeton de
 * rafraîchissement : il échange un jeton d'accès expiré contre un neuf sans
 * repasser par Discord.
 *
 * Chaque rafraîchissement remplace la session par une neuve, valable
 * LIFETIME_DAYS jours : un joueur qui revient au moins une fois par mois ne se
 * reconnecte jamais. L'ancienne reste acceptée ROTATION_GRACE_SECONDS secondes,
 * pour que deux onglets qui rafraîchissent en même temps ne se déconnectent pas
 * l'un l'autre.
 *
 * Seule l'empreinte du jeton est connue ici : la valeur en clair n'existe qu'au
 * moment de son émission, pour être remise au front.
 */
final readonly class PlayerSession
{
    public const string PREFIX = 'rft_';
    public const int LIFETIME_DAYS = 30;
    public const int ROTATION_GRACE_SECONDS = 30;

    public function __construct(
        public string $digest,
        public DiscordIdentity $player,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    public static function open(string $digest, DiscordIdentity $player, \DateTimeImmutable $now): self
    {
        return new self($digest, $player, $now->modify(sprintf('+%d days', self::LIFETIME_DAYS)));
    }

    public function isExpiredAt(\DateTimeImmutable $instant): bool
    {
        return $instant > $this->expiresAt;
    }

    /** Remplacée par une session neuve : n'est plus acceptée qu'un court instant. */
    public function retire(\DateTimeImmutable $now): self
    {
        $graceEnd = $now->modify(sprintf('+%d seconds', self::ROTATION_GRACE_SECONDS));

        return new self($this->digest, $this->player, min($this->expiresAt, $graceEnd));
    }
}
