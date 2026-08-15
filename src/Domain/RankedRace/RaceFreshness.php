<?php

namespace App\Domain\RankedRace;

/**
 * Fraîcheur des données d'un classement, pour un front qui interroge l'API en
 * boucle.
 *
 * L'âge est calculé côté serveur et non par différence de dates chez le client :
 * l'horloge d'un poste dérive souvent de plusieurs minutes, ce qui produit des
 * « mis à jour il y a -3 min ». Le client corrige ensuite avec le temps écoulé
 * depuis la réception, mesuré localement — une durée, pas une date.
 */
final readonly class RaceFreshness
{
    /** Cadence du cron refreshSummoners (cf. deploy/crontab.example). */
    public const int SNAPSHOT_INTERVAL_SECONDS = 1800;

    private function __construct(
        public \DateTimeImmutable $generatedAt,
        public ?\DateTimeImmutable $lastSnapshotAt,
        public ?\DateTimeImmutable $nextRefreshAt,
        public ?int $snapshotAgeSeconds,
    ) {
    }

    public static function at(\DateTimeImmutable $now, ?\DateTimeImmutable $lastSnapshotAt): self
    {
        // Aucun relevé : file jamais collectée, ou base fraîchement installée.
        if ($lastSnapshotAt === null) {
            return new self($now, null, null, null);
        }

        return new self(
            $now,
            $lastSnapshotAt,
            $lastSnapshotAt->modify(sprintf('+%d seconds', self::SNAPSHOT_INTERVAL_SECONDS)),
            // Jamais négatif : un relevé peut être daté dans le futur si les
            // horloges du serveur web et du cron divergent.
            max(0, $now->getTimestamp() - $lastSnapshotAt->getTimestamp()),
        );
    }
}
