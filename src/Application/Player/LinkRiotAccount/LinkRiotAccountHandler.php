<?php

namespace App\Application\Player\LinkRiotAccount;

use App\Domain\Player\RiotAccountAlreadyLinkedException;
use App\Domain\Player\RiotAccountNotTrackedException;
use App\Domain\Player\RiotId;
use App\Domain\Player\TrackedRiotAccount;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;

/**
 * Lie un compte Riot à un joueur Discord. Seuls les comptes déjà suivis
 * (ajoutés en administration, puuid connu) peuvent l'être : un joueur ne fait
 * pas entrer un compte dans les classements par lui-même.
 */
final readonly class LinkRiotAccountHandler
{
    public function __construct(private TrackedRiotAccountRepositoryInterface $accounts)
    {
    }

    public function handle(LinkRiotAccountCommand $command): TrackedRiotAccount
    {
        $riotId = new RiotId($command->riotId);
        $account = $this->accounts->findByRiotId($riotId)
            ?? throw new RiotAccountNotTrackedException(sprintf('Aucun compte suivi ne porte le Riot ID « %s ».', $riotId->value));

        // Déjà à lui : rien à faire, le front peut rejouer la demande sans risque.
        if ($account->isOwnedBy($command->player->userId)) {
            return $account;
        }

        // Un compte n'a qu'un propriétaire : le « reprendre » se fait en
        // administration, jamais par un autre joueur.
        if ($account->ownerDiscordId !== null) {
            throw new RiotAccountAlreadyLinkedException(sprintf('Le compte « %s » est déjà lié à un autre joueur.', $account->riotId));
        }

        $this->accounts->linkTo($account->puuid, $command->player);

        return new TrackedRiotAccount($account->riotId, $account->puuid, $command->player->userId);
    }
}
