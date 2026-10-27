<?php

namespace App\Application\Player\SyncRiotIds;

final readonly class SyncRiotIdsReport
{
    /**
     * @param list<array{from: string, to: string}> $renamed comptes dont le Riot ID a changé
     * @param list<string> $failed Riot ID des comptes laissés tels quels faute de réponse exploitable de Riot
     */
    public function __construct(
        public array $renamed,
        public int $unchanged,
        public array $failed,
    ) {
    }
}
