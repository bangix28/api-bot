<?php

namespace App\Tests\Domain\Player;

use App\Domain\Player\RiotId;
use App\Domain\Player\RiotIdDirectoryInterface;
use App\Domain\Player\RiotIdLookupFailedException;

/** Riot ID actuels, par puuid ; un puuid absent est inconnu de Riot, un puuid « en panne » fait échouer l'appel. */
final class ScriptedRiotIdDirectory implements RiotIdDirectoryInterface
{
    /** @var array<string, string> */
    private array $riotIds = [];

    /** @var list<string> */
    private array $unavailable = [];

    /** @var list<string> puuid demandés, dans l'ordre */
    public array $lookups = [];

    public function knows(string $puuid, string $riotId): self
    {
        $this->riotIds[$puuid] = $riotId;

        return $this;
    }

    public function failsFor(string $puuid): self
    {
        $this->unavailable[] = $puuid;

        return $this;
    }

    public function currentRiotIdOf(string $puuid): ?RiotId
    {
        $this->lookups[] = $puuid;

        if (in_array($puuid, $this->unavailable, true)) {
            throw new RiotIdLookupFailedException('Riot a répondu 503 pour ce compte.');
        }

        return isset($this->riotIds[$puuid]) ? new RiotId($this->riotIds[$puuid]) : null;
    }
}
