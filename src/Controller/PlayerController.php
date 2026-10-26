<?php

namespace App\Controller;

use App\Application\Player\GetProfile\GetProfileHandler;
use App\Application\Player\GetProfile\PlayerProfileView;
use App\Application\Player\LinkRiotAccount\LinkRiotAccountCommand;
use App\Application\Player\LinkRiotAccount\LinkRiotAccountHandler;
use App\Application\Player\LoginWithDiscord\LoginWithDiscordCommand;
use App\Application\Player\LoginWithDiscord\LoginWithDiscordHandler;
use App\Domain\Player\DiscordLoginFailedException;
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
 * Joueurs connectés par Discord : connexion, profil, liaison des comptes Riot.
 * Contrat pour le front : docs/front/connexion-discord.md.
 */
class PlayerController extends AbstractController
{
    #[Route('/api/auth/discord', name: 'api_player_login', methods: ['POST'])]
    public function login(Request $request, LoginWithDiscordHandler $handler, JWTTokenManagerInterface $tokenManager, GetProfileHandler $profile): JsonResponse
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

        $token = $tokenManager->createFromPayload(new PlayerUser($identity), [PlayerUserProvider::USERNAME_CLAIM => $identity->username]);

        return new JsonResponse(['token' => $token, 'player' => self::profile($profile->handle($identity))]);
    }

    #[Route('/api/me', name: 'api_player_profile', methods: ['GET'])]
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
