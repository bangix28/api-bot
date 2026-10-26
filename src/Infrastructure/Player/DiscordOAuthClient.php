<?php

namespace App\Infrastructure\Player;

use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\DiscordLoginFailedException;
use App\Domain\Player\DiscordOAuthClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Échange un code d'autorisation Discord contre l'identité du joueur
 * (https://discord.com/developers/docs/topics/oauth2, scope « identify »).
 *
 * Le secret de l'application ne quitte jamais le serveur : c'est pour lui que
 * l'échange se fait ici et non dans le front.
 */
final readonly class DiscordOAuthClient implements DiscordOAuthClientInterface
{
    private const string API = 'https://discord.com/api/v10';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function identityFromCode(string $code): DiscordIdentity
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new DiscordLoginFailedException('Connexion Discord non configurée : DISCORD_CLIENT_ID et DISCORD_CLIENT_SECRET sont vides.');
        }

        try {
            $token = $this->httpClient->request('POST', self::API . '/oauth2/token', [
                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                ],
                'auth_basic' => [$this->clientId, $this->clientSecret],
            ]);

            // Code refusé, expiré ou déjà utilisé, ou redirect_uri différente de
            // celle utilisée par le front : Discord répond 400 invalid_grant.
            if ($token->getStatusCode() !== 200) {
                $this->logger->warning('Échange du code Discord refusé', [
                    'status' => $token->getStatusCode(),
                    'error' => $token->toArray(false)['error'] ?? null,
                ]);

                throw new DiscordLoginFailedException('Discord a refusé le code d\'autorisation.');
            }

            $accessToken = $token->toArray()['access_token'] ?? null;
            if (!is_string($accessToken)) {
                throw new DiscordLoginFailedException('Discord n\'a pas renvoyé de jeton d\'accès.');
            }

            $discordUser = $this->httpClient->request('GET', self::API . '/users/@me', [
                'auth_bearer' => $accessToken,
            ])->toArray();

            if (!isset($discordUser['id'], $discordUser['username'])) {
                throw new DiscordLoginFailedException('Discord n\'a pas renvoyé l\'identité du joueur.');
            }

            return new DiscordIdentity((string) $discordUser['id'], (string) $discordUser['username']);
        } catch (ExceptionInterface | \InvalidArgumentException $exception) {
            $this->logger->warning('Connexion Discord impossible', ['exception' => $exception]);

            throw new DiscordLoginFailedException('Discord est injoignable ou a renvoyé une réponse inattendue.', previous: $exception);
        }
    }
}
