<?php

namespace App\Domain\Player;

interface PlayerSessionRepositoryInterface
{
    /** Enregistre une session neuve, ou met à jour celle de même empreinte. */
    public function save(PlayerSession $session): void;

    /** Session correspondant à cette valeur en clair, expirée ou non ; null si inconnue. */
    public function findByToken(string $plainToken): ?PlayerSession;

    public function delete(PlayerSession $session): void;
}
