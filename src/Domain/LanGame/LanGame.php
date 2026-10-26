<?php

namespace App\Domain\LanGame;

/**
 * Une partie jouée pendant une LAN, reconstituée à partir des relevés des
 * agents (ADR-0005, décision 2). La Live Client Data API ne donnant pas
 * d'identifiant de partie, elle est reconnue par son mode, ses 10 joueurs et
 * son heure de début estimée (réception − temps de jeu).
 *
 * Immuable : chaque étape renvoie une nouvelle partie.
 */
final readonly class LanGame
{
    /** Au-delà, le premier relevé arrive trop tard pour engager des cartes. */
    public const int LATE_START_SECONDS = 90;
    /** Écart toléré entre les heures de début estimées par deux relevés de la même partie. */
    public const int START_TOLERANCE_SECONDS = 60;
    /** Sans relevé pendant ce délai, la partie est abandonnée. */
    public const int ABANDON_AFTER_SECONDS = 3600;

    private function __construct(
        public string $gameId,
        public int $lanId,
        public string $gameMode,
        public string $playersFingerprint,
        public \DateTimeImmutable $estimatedStartAt,
        public LanGameStatus $status,
        public \DateTimeImmutable $lastReportAt,
        /** Scores des joueurs au règlement, pour recouper les relevés finaux suivants. */
        public ?string $settledScores,
    ) {
    }

    /** Premier relevé d'une partie inconnue. */
    public static function open(string $gameId, int $lanId, GameSnapshot $snapshot, \DateTimeImmutable $receivedAt): self
    {
        $status = $snapshot->gameTimeSeconds <= self::LATE_START_SECONDS
            ? LanGameStatus::IN_PROGRESS
            : LanGameStatus::JOINED_TOO_LATE;

        return new self(
            $gameId, $lanId, $snapshot->gameMode, $snapshot->playersFingerprint(),
            self::estimatedStart($snapshot, $receivedAt), $status, $receivedAt, null,
        );
    }

    public static function restore(
        string $gameId,
        int $lanId,
        string $gameMode,
        string $playersFingerprint,
        \DateTimeImmutable $estimatedStartAt,
        LanGameStatus $status,
        \DateTimeImmutable $lastReportAt,
        ?string $settledScores,
    ): self {
        return new self($gameId, $lanId, $gameMode, $playersFingerprint, $estimatedStartAt, $status, $lastReportAt, $settledScores);
    }

    /** Identifiant stable, fixé au premier relevé : c'est sur lui que les cartes sont verrouillées. */
    public static function idFor(int $lanId, GameSnapshot $snapshot, \DateTimeImmutable $receivedAt): string
    {
        return sprintf(
            'lan%d-%d-%s',
            $lanId,
            self::estimatedStart($snapshot, $receivedAt)->getTimestamp(),
            substr(hash('sha256', $snapshot->playersFingerprint()), 0, 12),
        );
    }

    public static function estimatedStart(GameSnapshot $snapshot, \DateTimeImmutable $receivedAt): \DateTimeImmutable
    {
        return $receivedAt->modify(sprintf('-%d seconds', (int) $snapshot->gameTimeSeconds));
    }

    /** Ce relevé décrit-il cette partie ? Même mode, mêmes joueurs, même début à une minute près. */
    public function matches(GameSnapshot $snapshot, \DateTimeImmutable $receivedAt): bool
    {
        $startGap = abs(self::estimatedStart($snapshot, $receivedAt)->getTimestamp() - $this->estimatedStartAt->getTimestamp());

        return $snapshot->gameMode === $this->gameMode
            && $snapshot->playersFingerprint() === $this->playersFingerprint
            && $startGap <= self::START_TOLERANCE_SECONDS;
    }

    public function engagesCards(): bool
    {
        return $this->status === LanGameStatus::IN_PROGRESS;
    }

    public function withReportAt(\DateTimeImmutable $receivedAt): self
    {
        return new self(
            $this->gameId, $this->lanId, $this->gameMode, $this->playersFingerprint,
            $this->estimatedStartAt, $this->status, $receivedAt, $this->settledScores,
        );
    }

    public function settle(GameSnapshot $finalSnapshot): self
    {
        if ($this->status !== LanGameStatus::IN_PROGRESS) {
            throw new \LogicException(sprintf('La partie %s n\'est pas en cours.', $this->gameId));
        }

        return new self(
            $this->gameId, $this->lanId, $this->gameMode, $this->playersFingerprint,
            $this->estimatedStartAt, LanGameStatus::SETTLED, $this->lastReportAt, self::scoresOf($finalSnapshot),
        );
    }

    /**
     * Un autre agent rapporte la fin d'une partie déjà réglée : ses scores
     * concordent-ils avec ceux du règlement ? (ADR-0005, décision 4)
     */
    public function agreesWith(GameSnapshot $finalSnapshot): bool
    {
        return $this->settledScores === self::scoresOf($finalSnapshot);
    }

    public function isAbandonedAt(\DateTimeImmutable $now): bool
    {
        return $this->status === LanGameStatus::IN_PROGRESS
            && $now->getTimestamp() - $this->lastReportAt->getTimestamp() > self::ABANDON_AFTER_SECONDS;
    }

    public function abandon(): self
    {
        if ($this->status !== LanGameStatus::IN_PROGRESS) {
            throw new \LogicException(sprintf('La partie %s n\'est pas en cours.', $this->gameId));
        }

        return new self(
            $this->gameId, $this->lanId, $this->gameMode, $this->playersFingerprint,
            $this->estimatedStartAt, LanGameStatus::ABANDONED, $this->lastReportAt, null,
        );
    }

    /** Kills, morts et assistances de chaque joueur, sous une forme stable et comparable. */
    private static function scoresOf(GameSnapshot $snapshot): string
    {
        $scores = [];
        foreach ($snapshot->players as $player) {
            $scores[mb_strtolower($player->riotId)] = [$player->kills, $player->deaths, $player->assists];
        }
        ksort($scores);

        return json_encode($scores, JSON_THROW_ON_ERROR);
    }
}
