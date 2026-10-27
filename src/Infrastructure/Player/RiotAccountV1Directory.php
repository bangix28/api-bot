<?php

namespace App\Infrastructure\Player;

use App\Domain\Player\InvalidRiotIdException;
use App\Domain\Player\RiotId;
use App\Domain\Player\RiotIdDirectoryInterface;
use App\Domain\Player\RiotIdLookupFailedException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Riot ID d'un compte via ACCOUNT-V1 (https://developer.riotgames.com/apis#account-v1),
 * que la bibliothèque LeagueAPI ne couvre pas. Routage régional : les comptes
 * EUW sont servis par « europe ».
 */
final readonly class RiotAccountV1Directory implements RiotIdDirectoryInterface
{
    private const string ACCOUNT_BY_PUUID = 'https://europe.api.riotgames.com/riot/account/v1/accounts/by-puuid/%s';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%app.riot.api.token%')]
        private string $riotApiToken,
    ) {
    }

    public function currentRiotIdOf(string $puuid): ?RiotId
    {
        try {
            $response = $this->httpClient->request('GET', sprintf(self::ACCOUNT_BY_PUUID, rawurlencode($puuid)), [
                'headers' => ['X-Riot-Token' => $this->riotApiToken],
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();
            if ($status === 404) {
                return null;
            }
            if ($status !== 200) {
                // 401/403 : clé expirée ; 429 : limite d'appels ; 5xx : panne chez Riot.
                throw new RiotIdLookupFailedException(sprintf('Riot a répondu %d pour ce compte.', $status));
            }

            $account = $response->toArray();
        } catch (ExceptionInterface $exception) {
            throw new RiotIdLookupFailedException('Riot est injoignable ou a renvoyé une réponse illisible.', previous: $exception);
        }

        $gameName = $account['gameName'] ?? null;
        $tagLine = $account['tagLine'] ?? null;
        if (!is_string($gameName) || !is_string($tagLine)) {
            throw new RiotIdLookupFailedException('Riot n\'a pas renvoyé de pseudo et de tag pour ce compte.');
        }

        try {
            return new RiotId($gameName . '#' . $tagLine);
        } catch (InvalidRiotIdException $exception) {
            throw new RiotIdLookupFailedException('Riot a renvoyé un Riot ID inexploitable.', previous: $exception);
        }
    }
}
