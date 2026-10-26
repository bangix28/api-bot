<?php

namespace App\Domain\Agent;

use App\Domain\Player\DiscordIdentity;

/**
 * Une demande d'appairage d'agent (RFC 8628) : l'agent garde le deviceCode
 * secret, le joueur saisit le userCode sur le front, où il l'approuve. Seule
 * l'empreinte du deviceCode est conservée (SecretDigest).
 *
 * Immuable : chaque étape renvoie une nouvelle demande.
 */
final readonly class DeviceAuthorization
{
    public const int LIFETIME_SECONDS = 600;
    public const int POLL_INTERVAL_SECONDS = 5;

    private function __construct(
        public string $deviceCodeDigest,
        public UserCode $userCode,
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $lastPolledAt,
        public ?DeviceApproval $approval,
        public bool $consumed,
    ) {
    }

    public static function request(string $deviceCode, UserCode $userCode, \DateTimeImmutable $now): self
    {
        return new self(SecretDigest::of($deviceCode), $userCode, $now->modify(sprintf('+%d seconds', self::LIFETIME_SECONDS)), null, null, false);
    }

    public static function restore(
        string $deviceCodeDigest,
        UserCode $userCode,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $lastPolledAt,
        ?DeviceApproval $approval,
        bool $consumed,
    ): self {
        return new self($deviceCodeDigest, $userCode, $expiresAt, $lastPolledAt, $approval, $consumed);
    }

    public function isExpiredAt(\DateTimeImmutable $instant): bool
    {
        return $instant > $this->expiresAt;
    }

    /**
     * Le joueur connecté saisit le code : l'agent lui appartient désormais.
     * Un code expiré ou déjà approuvé est refusé comme inconnu, pour ne rien
     * apprendre à qui essaierait des codes au hasard. (Un code échangé a
     * forcément été approuvé : il est couvert.)
     */
    public function approve(DiscordIdentity $player, int $lanId, \DateTimeImmutable $now): self
    {
        if ($this->isExpiredAt($now) || $this->approval !== null) {
            throw new InvalidUserCodeException('Code inconnu ou expiré : relance la connexion depuis l\'agent.');
        }

        return new self($this->deviceCodeDigest, $this->userCode, $this->expiresAt, $this->lastPolledAt, new DeviceApproval($player, $lanId), false);
    }

    /** L'agent interroge : où en est l'appairage ? Retient l'heure de l'interrogation. */
    public function poll(\DateTimeImmutable $now): DevicePollOutcome
    {
        if ($this->consumed) {
            throw new InvalidDeviceCodeException('Ce code a déjà servi.');
        }

        $polled = new self($this->deviceCodeDigest, $this->userCode, $this->expiresAt, $now, $this->approval, false);

        $status = match (true) {
            $this->approval !== null => DevicePollStatus::APPROVED,
            $this->isExpiredAt($now) => DevicePollStatus::EXPIRED,
            $this->lastPolledAt !== null
                && $now->getTimestamp() - $this->lastPolledAt->getTimestamp() < self::POLL_INTERVAL_SECONDS => DevicePollStatus::SLOW_DOWN,
            default => DevicePollStatus::PENDING,
        };

        return new DevicePollOutcome($polled, $status);
    }

    /** Le jeton vient d'être émis : le code ne resservira plus. */
    public function consume(): self
    {
        if ($this->approval === null) {
            throw new \LogicException('Un appairage non approuvé ne s\'échange pas contre un jeton.');
        }

        return new self($this->deviceCodeDigest, $this->userCode, $this->expiresAt, $this->lastPolledAt, $this->approval, true);
    }
}
