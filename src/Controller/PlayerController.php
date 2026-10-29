<?php

namespace App\Controller;

use App\Application\Player\EndSession\EndSessionHandler;
use App\Application\Player\GetProfile\GetProfileHandler;
use App\Application\Player\GetProfile\PlayerProfileView;
use App\Application\Player\IssuedSession;
use App\Application\Player\LinkRiotAccount\LinkRiotAccountCommand;
use App\Application\Player\LinkRiotAccount\LinkRiotAccountHandler;
use App\Application\Player\LoginWithDiscord\LoginWithDiscordCommand;
use App\Application\Player\LoginWithDiscord\LoginWithDiscordHandler;
use App\Application\Player\OpenSession\OpenSessionHandler;
use App\Application\Player\RefreshSession\RefreshSessionHandler;
use App\Domain\Player\DiscordLoginFailedException;
use App\Domain\Player\InvalidRefreshTokenException;
use App\Domain\Player\InvalidRiotIdException;
use App\Domain\Player\RiotAccountAlreadyLinkedException;
use App\Domain\Player\RiotAccountNotTrackedException;
use App\Infrastructure\Security\PlayerUser;
use App\Infrastructure\Security\PlayerUserProvider;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Joueurs connectés par Discord : connexion, rafraîchissement de session,
 * déconnexion, profil, liaison des comptes Riot.
 * Contrat pour le front : docs/front/connexion-discord.md.
 */
class PlayerController extends AbstractController
{
    #[Route('/api/auth/discord', name: 'api_player_login', methods: ['POST'])]
    public function login(Request $request, LoginWithDiscordHandler $handler, OpenSessionHandler $openSession, JWTTokenManagerInterface $tokenManager, GetProfileHandler $profile): JsonResponse
    {
        $code = $request->getPayload()->get('code');
        if (!is_string($code) || $code === '') {
            return self::error(Response::HTTP_BAD_REQUEST, 'missing_code', 'Le code d\'autorisation Discord est obligatoire.');
        }

        try {
            $identity = $handler->handle(new LoginWithDiscordCommand($code));
        } catch (DiscordLoginFailedException $exception) {
            return self::error(Response::HTTP_UNAUTHORIZED, 'discord_login_failed', $exception->getMessage());
        }

        return self::session($openSession->handle($identity), $tokenManager, $profile);
    }

    #[Route('/api/auth/refresh', name: 'api_player_refresh', methods: ['POST'])]
    public function refresh(Request $request, RefreshSessionHandler $handler, JWTTokenManagerInterface $tokenManager, GetProfileHandler $profile): JsonResponse
    {
        $refreshToken = $request->getPayload()->get('refreshToken');
        if (!is_string($refreshToken) || $refreshToken === '') {
            return self::error(Response::HTTP_BAD_REQUEST, 'missing_refresh_token', 'Le jeton de rafraîchissement est obligatoire.');
        }

        try {
            $issued = $handler->handle($refreshToken);
        } catch (InvalidRefreshTokenException $exception) {
            return self::error(Response::HTTP_UNAUTHORIZED, 'invalid_refresh_token', $exception->getMessage());
        }

        return self::session($issued, $tokenManager, $profile);
    }

    #[Route('/api/auth/logout', name: 'api_player_logout', methods: ['POST'])]
    public function logout(Request $request, EndSessionHandler $handler): Response
    {
        $refreshToken = $request->getPayload()->get('refreshToken');
        if (!is_string($refreshToken) || $refreshToken === '') {
            return self::error(Response::HTTP_BAD_REQUEST, 'missing_refresh_token', 'Le jeton de rafraîchissement est obligatoire.');
        }

        $handler->handle($refreshToken);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/me',name: 'api_player_profile', methods: ['GET'])]
    public function me(#[CurrentUser] PlayerUser $player, GetProfileHandler $profile): JsonResponse
    {
        return new JsonResponse(self::profile($profile->handle($player->discord)));
    }

    #[Route('/api/me/riot-accounts', name: 'api_player_link_riot_account', methods: ['POST'])]
    public function linkRiotAccount(#[CurrentUser] PlayerUser $player, Request $request, LinkRiotAccountHandler $handler, GetProfileHandler $profile): JsonResponse
    {
        $riotId = $request->getPayload()->get('riotId');
        if (!is_string($riotId)) {
            return self::error(Response::HTTP_BAD_REQUEST, 'invalid_riot_id', 'Le Riot ID est obligatoire (« Pseudo#TAG »).');
        }

        try {
            $handler->handle(new LinkRiotAccountCommand($player->discord, $riotId));
        } catch (InvalidRiotIdException $exception) {
            return self::error(Response::HTTP_BAD_REQUEST, 'invalid_riot_id', $exception->getMessage());
        } catch (RiotAccountNotTrackedException $exception) {
            return self::error(Response::HTTP_NOT_FOUND, 'riot_account_not_tracked', $exception->getMessage());
        } catch (RiotAccountAlreadyLinkedException $exception) {
            return self::error(Response::HTTP_CONFLICT, 'riot_account_already_linked', $exception->getMessage());
        }

        return new JsonResponse(self::profile($profile->handle($player->discord)));
    }

    private static function session(IssuedSession $issued, JWTTokenManagerInterface $tokenManager, GetProfileHandler $profile): JsonResponse
    {
        $player = $issued->session->player;
        $token = $tokenManager->createFromPayload(new PlayerUser($player), [PlayerUserProvider::USERNAME_CLAIM => $player->username]);

        return new JsonResponse([
            'token' => $token,
            'refreshToken' => $issued->plainRefreshToken,
            'player' => self::profile($profile->handle($player)),
        ]);
    }

    /** @return array<string, mixed> */
    private static function profile(PlayerProfileView $view): array
    {
        return [
            'discordId' => $view->discordId,
            'discordUsername' => $view->discordUsername,
            'riotIds' => $view->riotIds,
        ];
    }

    private static function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message], $status);
    }
}
