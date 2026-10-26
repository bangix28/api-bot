<?php

namespace App\Application\LanChallenge\ResolveGame;

use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;
use App\Domain\LanChallenge\RoundResolver;

/**
 * Une partie est terminée et son relevé est établi : on règle les cartes qui y
 * étaient engagées et on distribue l'offre suivante.
 *
 * Le relevé arrive déjà recoupé : la confiance dans les données des agents
 * (ADR-0005, décision 4) se décide en amont, pas ici.
 */
final readonly class ResolveGameHandler
{
    public function __construct(
        private PlayerRunRepositoryInterface $runs,
        private RoundResolver $resolver,
    ) {
    }

    /** @return PlayerRun[] parcours réglés ; vide si aucune carte n'était engagée */
    public function handle(ResolveGameCommand $command): array
    {
        $settled = $this->resolver->resolve($command->report, $this->runs->findByLan($command->lanId));

        foreach ($settled as $run) {
            $this->runs->save($run);
        }

        return $settled;
    }
}
