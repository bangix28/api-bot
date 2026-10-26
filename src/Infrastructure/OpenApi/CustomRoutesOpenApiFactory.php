<?php

namespace App\Infrastructure\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use App\Application\LanGame\ReceiveReport\IgnoredReason;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Documente dans Swagger les routes servies par des contrôleurs Symfony, qu'API
 * Platform ne voit pas : connexion Discord des joueurs, défis de LAN, agent.
 *
 * Contrats détaillés : docs/front/connexion-discord.md, docs/front/defis-lan.md,
 * docs/agent/contrat-releve.md. Toute route /api d'un contrôleur doit figurer
 * ici : CustomRoutesOpenApiFactoryTest le vérifie contre le routeur.
 */
#[AsDecorator('api_platform.openapi.factory')]
final readonly class CustomRoutesOpenApiFactory implements OpenApiFactoryInterface
{
    private const string PLAYER_TAG = 'Joueur (Discord)';
    private const string CHALLENGE_TAG = 'Défis de LAN';
    private const string AGENT_TAG = 'Agent de LAN';

    /** Schéma de sécurité du jeton d'agent ; « JWT » est déclaré par le bundle Lexik. */
    public const string AGENT_TOKEN_SCHEME = 'AgentToken';

    private const array JWT = [['JWT' => []]];
    private const array AGENT = [[self::AGENT_TOKEN_SCHEME => []]];
    private const array PUBLIC = [];

    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $components = $openApi->getComponents();

        $components->getSecuritySchemes()?->offsetSet(self::AGENT_TOKEN_SCHEME, new \ArrayObject([
            'type' => 'http',
            'scheme' => 'bearer',
            'description' => 'Jeton d\'agent « agt_… », obtenu par l\'appairage (POST /api/agent/v1/token).',
        ]));

        foreach (self::schemas() as $name => $schema) {
            $components->getSchemas()?->offsetSet($name, new \ArrayObject($schema));
        }

        foreach (self::operations() as [$path, $method, $operation]) {
            $pathItem = $openApi->getPaths()->getPath($path) ?? new PathItem();
            $openApi->getPaths()->addPath($path, $pathItem->{'with' . ucfirst($method)}($operation));
        }

        return $openApi;
    }

    /** @return list<array{string, string, Operation}> */
    private static function operations(): array
    {
        $lanId = new Parameter('lanId', 'path', 'Identifiant de la LAN', true, schema: ['type' => 'integer']);

        return [
            // Joueur
            ['/api/auth/discord', 'post', self::operation(
                'player_discord_login', self::PLAYER_TAG, self::PUBLIC,
                'Connecte un joueur avec le code renvoyé par Discord.',
                'Le front envoie le joueur sur Discord (scope « identify »), puis transmet ici le code reçu. Renvoie un JWT de joueur (1 h).',
                self::body(['code' => ['type' => 'string']]),
                [200 => ['Joueur connecté.', 'DiscordLogin'], 400 => ['missing_code'], 401 => ['discord_login_failed : code refusé, expiré, redirect_uri différente ou Discord injoignable.']],
            )],
            ['/api/me', 'get', self::operation(
                'player_profile', self::PLAYER_TAG, self::JWT, 'Profil du joueur connecté.', null, null,
                [200 => ['Profil.', 'PlayerProfile'], 401 => ['Jeton absent ou invalide.'], 403 => ['Jeton valide mais pas celui d\'un joueur.']],
            )],
            ['/api/me/riot-accounts', 'post', self::operation(
                'player_link_riot_account', self::PLAYER_TAG, self::JWT,
                'Lie un compte Riot suivi au joueur connecté.',
                'Seuls les comptes déjà suivis (ajoutés en administration) peuvent être liés. Casse ignorée ; rejouable sans risque.',
                self::body(['riotId' => ['type' => 'string', 'example' => 'Pseudo#EUW']]),
                [200 => ['Profil à jour.', 'PlayerProfile'], 400 => ['invalid_riot_id'], 404 => ['riot_account_not_tracked'], 409 => ['riot_account_already_linked']],
            )],
            ['/api/me/agent-devices/approve', 'post', self::operation(
                'player_approve_agent_device', self::AGENT_TAG, self::JWT,
                'Appaire l\'agent du joueur connecté avec le code qu\'il affiche.',
                'Le joueur saisit sur le front le code affiché par son agent (casse, espaces et tiret indifférents). L\'agent est rattaché à sa LAN en cours, sinon la prochaine.',
                self::body(['userCode' => ['type' => 'string', 'example' => 'K7QF-92PZ']]),
                [200 => ['Agent appairé.', 'AgentPairing'], 400 => ['invalid_user_code : code inconnu, expiré ou déjà utilisé.'], 409 => ['not_registered : inscrit à aucune LAN en cours ou à venir.']],
            )],

            // Défis de LAN
            ['/api/lan-challenges/cards', 'get', self::operation(
                'lan_challenge_cards', self::CHALLENGE_TAG, self::JWT,
                'Catalogue des cartes défis, raretés et règles.', 'Accessible au compte API du front comme aux joueurs.', null,
                [200 => ['Catalogue.', 'CardCatalogue']],
            )],
            ['/api/lan-events/{lanId}/standings', 'get', self::operation(
                'lan_challenge_standings', self::CHALLENGE_TAG, self::JWT,
                'Classement de la LAN.', 'Tous les participants, y compris ceux qui n\'ont pas rejoint. Une carte saboteur est masquée (secretCard).', null,
                [200 => ['Classement.', 'LanStandings'], 404 => ['lan_not_found']], [$lanId],
            )],
            ['/api/lan-events/{lanId}/feed', 'get', self::operation(
                'lan_challenge_feed', self::CHALLENGE_TAG, self::JWT,
                'Fil d\'exploits de la LAN, le plus récent en premier.', 'Manches réglées et sabotages visibles ; le saboteur n\'est jamais nommé.', null,
                [200 => ['Fil.', 'LanFeed'], 404 => ['lan_not_found']],
                [$lanId, new Parameter('limit', 'query', 'Nombre d\'entrées, de 1 à 50 (20 par défaut).', schema: ['type' => 'integer', 'default' => 20])],
            )],
            ['/api/lan-events/{lanId}/me', 'get', self::operation(
                'lan_challenge_my_run', self::CHALLENGE_TAG, self::JWT,
                'Parcours du joueur connecté dans la LAN.', 'Inclut les cibles possibles quand l\'offre contient une carte qui vise un joueur.', null,
                [200 => ['Parcours.', 'MyRun'], 403 => ['not_a_participant'], 404 => ['lan_not_found'], 409 => ['several_accounts_in_lan']], [$lanId],
            )],
            ['/api/lan-events/{lanId}/me/join', 'post', self::operation(
                'lan_challenge_join', self::CHALLENGE_TAG, self::JWT,
                'Rejoint les défis de la LAN et reçoit la première offre.', 'Rejouable sans risque : ne redistribue pas.', null,
                [200 => ['Parcours.', 'MyRun'], 403 => ['not_a_participant'], 404 => ['lan_not_found'], 409 => ['lan_finished | several_accounts_in_lan']], [$lanId],
            )],
            ['/api/lan-events/{lanId}/me/pick', 'post', self::operation(
                'lan_challenge_pick', self::CHALLENGE_TAG, self::JWT,
                'Choisit une carte de l\'offre.',
                'Carte de match : { slot }. Carte qui vise un joueur (needsTarget) : { slot, target } ; effet immédiat et offre neuve pour le saboteur.',
                self::body(['slot' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2], 'target' => ['type' => 'string', 'example' => 'Ami#EUW']], ['slot']),
                [200 => ['Parcours.', 'MyRun'], 400 => ['invalid_slot | target_required | target_not_allowed | invalid_target'], 403 => ['not_a_participant'], 404 => ['lan_not_found'], 409 => ['not_joined | no_offer | target_has_no_reroll | lan_finished']], [$lanId],
            )],
            ['/api/lan-events/{lanId}/me/reroll', 'post', self::operation(
                'lan_challenge_reroll', self::CHALLENGE_TAG, self::JWT,
                'Relance une carte de l\'offre.', 'Remplace la carte de l\'emplacement par une carte absente de l\'offre et consomme une relance.',
                self::body(['slot' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2]]),
                [200 => ['Parcours.', 'MyRun'], 400 => ['invalid_slot'], 403 => ['not_a_participant'], 404 => ['lan_not_found'], 409 => ['not_joined | no_offer | no_reroll_left | lan_finished']], [$lanId],
            )],

            // Agent
            ['/api/agent/v1/device', 'post', self::operation(
                'agent_request_device_code', self::AGENT_TAG, self::PUBLIC,
                'L\'agent demande un code d\'appairage.', 'Afficher userCode au joueur ; garder deviceCode secret.', null,
                [200 => ['Code d\'appairage.', 'AgentDeviceCode']],
            )],
            ['/api/agent/v1/token', 'post', self::operation(
                'agent_exchange_device_code', self::AGENT_TAG, self::PUBLIC,
                'L\'agent échange son deviceCode contre un jeton.', 'À interroger toutes les « interval » secondes jusqu\'à l\'approbation ; le jeton n\'est remis qu\'une fois.',
                self::body(['deviceCode' => ['type' => 'string']]),
                [200 => ['Jeton d\'agent.', 'AgentTokenIssued'], 400 => ['authorization_pending | slow_down | expired_token | invalid_grant']],
            )],
            ['/api/agent/v1/session', 'get', self::operation(
                'agent_session', self::AGENT_TAG, self::AGENT,
                'Session de l\'agent : LAN, comptes du joueur, réglages.', null, null,
                [200 => ['Session.', 'AgentSession'], 401 => ['invalid_token | token_expired']],
            )],
            ['/api/agent/v1/reports', 'post', self::operation(
                'agent_report', self::AGENT_TAG, self::AGENT,
                'L\'agent envoie un relevé de la partie en cours.',
                'Corps : enveloppe du contrat (§ 3) avec « liveClient », la réponse brute de /liveclientdata/allgamedata. Content-Encoding: gzip accepté ; 1 Mo au plus une fois décompressé. Le premier relevé d\'une partie verrouille les cartes, le relevé final les règle.',
                new RequestBody(
                    content: new \ArrayObject(['application/json' => new MediaType(new \ArrayObject(['$ref' => '#/components/schemas/AgentReport']))]),
                    required: true,
                ),
                [
                    202 => ['Relevé enregistré.', 'AgentReportAccepted'],
                    200 => ['Relevé valide mais sans objet : cesser d\'envoyer pour cette partie.', 'AgentReportIgnored'],
                    400 => ['invalid_report'],
                    401 => ['invalid_token | token_expired'],
                    403 => ['not_your_account : le joueur du poste n\'est pas un compte du joueur.'],
                    413 => ['report_too_large'],
                    429 => ['too_many_reports : attendre Retry-After secondes.'],
                ],
            )],
        ];
    }

    /**
     * @param array<int, array{0: string, 1?: string}> $responses statut => [description, schéma de réponse]
     * @param list<array<string, array<mixed>>> $security
     * @param Parameter[] $parameters
     */
    private static function operation(
        string $operationId,
        string $tag,
        array $security,
        string $summary,
        ?string $description,
        ?RequestBody $requestBody,
        array $responses,
        array $parameters = [],
    ): Operation {
        $documentedResponses = [];
        foreach ($responses as $status => $response) {
            $schema = ['$ref' => '#/components/schemas/' . ($response[1] ?? 'LanApiError')];
            $documentedResponses[$status] = new Response(
                $response[0],
                new \ArrayObject(['application/json' => new MediaType(new \ArrayObject($schema))]),
            );
        }

        return new Operation(
            operationId: $operationId,
            tags: [$tag],
            responses: $documentedResponses,
            summary: $summary,
            description: $description ?? '',
            parameters: $parameters,
            requestBody: $requestBody,
            security: $security,
        );
    }

    /**
     * @param array<string, array<mixed>> $properties
     * @param string[]|null $required par défaut, toutes les propriétés
     */
    private static function body(array $properties, ?array $required = null): RequestBody
    {
        return new RequestBody(
            content: new \ArrayObject(['application/json' => new MediaType(new \ArrayObject([
                'type' => 'object',
                'properties' => $properties,
                'required' => $required ?? array_keys($properties),
            ]))]),
            required: true,
        );
    }

    /** @return array<string, array<mixed>> */
    private static function schemas(): array
    {
        $ref = static fn(string $schema) => ['$ref' => '#/components/schemas/' . $schema];
        $nullableRef = static fn(string $schema) => ['anyOf' => [$ref($schema), ['type' => 'null']]];
        $listOf = static fn(array $items) => ['type' => 'array', 'items' => $items];
        $dateTime = ['type' => 'string', 'format' => 'date-time'];
        $riotIds = $listOf(['type' => 'string', 'example' => 'Pseudo#EUW']);

        return [
            'LanApiError' => ['type' => 'object', 'properties' => [
                'code' => ['type' => 'string', 'description' => 'Code stable, à utiliser dans le front.'],
                'message' => ['type' => 'string', 'description' => 'En français, affichable tel quel.'],
            ]],
            'PlayerProfile' => ['type' => 'object', 'properties' => [
                'discordId' => ['type' => 'string'],
                'discordUsername' => ['type' => 'string'],
                'riotIds' => $riotIds,
            ]],
            'DiscordLogin' => ['type' => 'object', 'properties' => [
                'token' => ['type' => 'string', 'description' => 'JWT du joueur, 1 h.'],
                'player' => $ref('PlayerProfile'),
            ]],
            'LanSummary' => ['type' => 'object', 'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'startsAt' => $dateTime,
                'endsAt' => $dateTime,
            ]],
            'Card' => ['type' => 'object', 'properties' => [
                'code' => ['type' => 'string', 'example' => 'triple_kill'],
                'label' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'rarity' => ['type' => 'string', 'enum' => ['silver', 'gold', 'prismatic', 'saboteur']],
                'points' => ['type' => 'integer'],
                'secret' => ['type' => 'boolean', 'description' => 'Carte saboteur : ne jamais l\'afficher aux autres joueurs.'],
                'needsTarget' => ['type' => 'boolean', 'description' => 'Choisir cette carte demande une cible.'],
            ]],
            'CardCatalogue' => ['type' => 'object', 'properties' => [
                'cards' => $listOf($ref('Card')),
                'rarities' => $listOf(['type' => 'object', 'properties' => [
                    'rarity' => ['type' => 'string'],
                    'points' => ['type' => 'integer'],
                    'drawWeight' => ['type' => 'integer', 'description' => 'Chance sur 100 par emplacement d\'offre.'],
                ]]),
                'rules' => ['type' => 'object', 'properties' => [
                    'initialRerolls' => ['type' => 'integer'],
                    'roundBonusPercent' => ['type' => 'integer'],
                    'cursePercent' => ['type' => 'integer'],
                ]],
            ]],
            'RoundResult' => ['type' => 'object', 'properties' => [
                'gameId' => ['type' => 'string'],
                'card' => $ref('Card'),
                'verdict' => ['type' => 'string', 'enum' => ['success', 'failure', 'unknown']],
                'points' => ['type' => 'integer'],
                'roundBonus' => ['type' => 'boolean'],
                'curseApplied' => ['type' => 'boolean'],
            ]],
            'PlayerRun' => ['type' => 'object', 'properties' => [
                'score' => ['type' => 'integer'],
                'rerollsLeft' => ['type' => 'integer'],
                'offer' => ['anyOf' => [$listOf($ref('Card')), ['type' => 'null']]],
                'activeCard' => $nullableRef('Card'),
                'cardLocked' => ['type' => 'boolean'],
                'results' => $listOf($ref('RoundResult')),
            ]],
            'MyRun' => ['type' => 'object', 'properties' => [
                'lan' => $ref('LanSummary'),
                'riotId' => ['type' => 'string'],
                'joined' => ['type' => 'boolean'],
                'run' => $nullableRef('PlayerRun'),
                'sabotageTargets' => $listOf(['type' => 'object', 'properties' => [
                    'riotId' => ['type' => 'string'],
                    'canBePickpocketed' => ['type' => 'boolean'],
                ]]),
            ]],
            'LanStandings' => ['type' => 'object', 'properties' => [
                'lan' => $ref('LanSummary'),
                'entries' => $listOf(['type' => 'object', 'properties' => [
                    'rank' => ['type' => 'integer'],
                    'riotId' => ['type' => 'string'],
                    'joined' => ['type' => 'boolean'],
                    'score' => ['type' => 'integer'],
                    'successes' => ['type' => 'integer'],
                    'roundsPlayed' => ['type' => 'integer'],
                    'rerollsLeft' => ['type' => 'integer'],
                    'activeCard' => $nullableRef('Card'),
                    'secretCard' => ['type' => 'boolean'],
                    'cardLocked' => ['type' => 'boolean'],
                ]]),
            ]],
            'LanFeed' => ['type' => 'object', 'properties' => [
                'entries' => $listOf(['type' => 'object', 'properties' => [
                    'kind' => ['type' => 'string', 'enum' => ['round', 'sabotage']],
                    'riotId' => ['type' => 'string', 'description' => 'Joueur de la manche, ou cible du sabotage.'],
                    'card' => $ref('Card'),
                    'gameId' => ['type' => ['string', 'null']],
                    'verdict' => ['type' => ['string', 'null'], 'enum' => ['success', 'failure', 'unknown', null]],
                    'points' => ['type' => 'integer'],
                    'roundBonus' => ['type' => 'boolean'],
                    'curseApplied' => ['type' => 'boolean'],
                    'occurredAt' => $dateTime,
                ]]),
            ]],
            'AgentDeviceCode' => ['type' => 'object', 'properties' => [
                'userCode' => ['type' => 'string', 'example' => 'K7QF-92PZ'],
                'deviceCode' => ['type' => 'string', 'description' => 'Secret de l\'agent, à ne jamais afficher.'],
                'verificationUrl' => ['type' => 'string'],
                'expiresIn' => ['type' => 'integer', 'example' => 600],
                'interval' => ['type' => 'integer', 'example' => 5],
            ]],
            'AgentPairing' => ['type' => 'object', 'properties' => [
                'player' => ['type' => 'object', 'properties' => ['riotIds' => $riotIds]],
                'lan' => $ref('LanSummary'),
            ]],
            'AgentTokenIssued' => ['type' => 'object', 'properties' => [
                'token' => ['type' => 'string', 'example' => 'agt_3f9c…'],
                'expiresAt' => $dateTime,
                'player' => ['type' => 'object', 'properties' => ['riotIds' => $riotIds]],
                'lan' => $ref('LanSummary'),
            ]],
            'AgentSession' => ['type' => 'object', 'properties' => [
                'player' => ['type' => 'object', 'properties' => ['riotIds' => $riotIds]],
                'lan' => ['allOf' => [$ref('LanSummary'), ['type' => 'object', 'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['upcoming', 'in_progress']],
                ]]]],
                'acceptedGameModes' => $listOf(['type' => 'string', 'example' => 'KIWI']),
                'reportIntervalSeconds' => ['type' => 'integer'],
            ]],
            'AgentReport' => ['type' => 'object', 'required' => ['agentVersion', 'sessionId', 'sequence', 'final', 'endReason', 'observedAt', 'liveClient'], 'properties' => [
                'agentVersion' => ['type' => 'string', 'example' => '1.0.0'],
                'sessionId' => ['type' => 'string', 'format' => 'uuid', 'description' => 'Tiré par l\'agent au début de chaque partie.'],
                'sequence' => ['type' => 'integer', 'minimum' => 1],
                'final' => ['type' => 'boolean'],
                'endReason' => ['type' => ['string', 'null'], 'enum' => ['game_end', 'client_closed', null], 'description' => 'Obligatoire si final, null sinon.'],
                'observedAt' => $dateTime,
                'liveClient' => ['type' => 'object', 'description' => 'Réponse de GET /liveclientdata/allgamedata, telle quelle.', 'required' => ['activePlayer', 'allPlayers', 'events', 'gameData']],
            ]],
            'AgentReportAccepted' => ['type' => 'object', 'properties' => [
                'status' => ['type' => 'string', 'enum' => ['accepted']],
            ]],
            'AgentReportIgnored' => ['type' => 'object', 'properties' => [
                'status' => ['type' => 'string', 'enum' => ['ignored']],
                'reason' => ['type' => 'string', 'enum' => array_map(static fn(IgnoredReason $reason) => $reason->value, IgnoredReason::cases())],
            ]],
        ];
    }
}
