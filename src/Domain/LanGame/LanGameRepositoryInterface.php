<?php

namespace App\Domain\LanGame;

use App\Domain\LanChallenge\LanQueue;

interface LanGameRepositoryInterface
{
    public function save(LanGame $game): void;

    /**
     * Parties de la LAN de même file et mêmes joueurs ; l'appelant retient celle
     * dont l'heure de début correspond (LanGame::matches).
     *
     * @return LanGame[]
     */
    public function findCandidates(int $lanId, LanQueue $queue, string $playersFingerprint): array;

    /** Partie déjà rattachée à cette session d'agent (même jeton, même sessionId). */
    public function findBySession(string $tokenDigest, string $sessionId): ?LanGame;

    /** @return LanGame[] parties en cours */
    public function findInProgress(): array;
}
