<?php

namespace App\Domain\LanGame;

interface LanGameRepositoryInterface
{
    public function save(LanGame $game): void;

    /**
     * Parties de la LAN de même mode et mêmes joueurs ; l'appelant retient celle
     * dont l'heure de début correspond (LanGame::matches).
     *
     * @return LanGame[]
     */
    public function findCandidates(int $lanId, string $gameMode, string $playersFingerprint): array;

    /** Partie déjà rattachée à cette session d'agent (même jeton, même sessionId). */
    public function findBySession(string $tokenDigest, string $sessionId): ?LanGame;

    /** @return LanGame[] parties en cours */
    public function findInProgress(): array;
}
