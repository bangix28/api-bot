<?php

namespace App\Tests\Infrastructure\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use App\Infrastructure\OpenApi\CustomRoutesOpenApiFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Garde-fou contre l'oubli : chaque route /api déclarée par un contrôleur
 * (#[Route]) doit apparaître dans Swagger. Les ressources API Platform et
 * /api/login sont documentées automatiquement, pas les contrôleurs.
 */
class CustomRoutesOpenApiFactoryTest extends TestCase
{
    private const string CONTROLLER_DIRECTORY = __DIR__ . '/../../../src/Controller';

    private static function openApi(): OpenApi
    {
        $empty = new class implements OpenApiFactoryInterface {
            public function __invoke(array $context = []): OpenApi
            {
                return new OpenApi(
                    new Info('API', '1'),
                    [],
                    new Paths(),
                    new Components(new \ArrayObject(), securitySchemes: new \ArrayObject(['JWT' => new \ArrayObject(['type' => 'http'])])),
                );
            }
        };

        return (new CustomRoutesOpenApiFactory($empty))();
    }

    /** @return list<string> « METHODE /chemin » de chaque route /api déclarée par un contrôleur */
    private static function controllerApiRoutes(): array
    {
        $routes = [];
        foreach (glob(self::CONTROLLER_DIRECTORY . '/*Controller.php') as $file) {
            $class = new \ReflectionClass('App\\Controller\\' . basename($file, '.php'));
            foreach ($class->getMethods() as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $route = $attribute->newInstance();
                    if (!str_starts_with((string) $route->path, '/api/')) {
                        continue;
                    }
                    foreach ($route->methods as $httpMethod) {
                        $routes[] = strtoupper($httpMethod) . ' ' . $route->path;
                    }
                }
            }
        }
        sort($routes);

        return $routes;
    }

    /** @return list<string> */
    private static function documentedOperations(OpenApi $openApi): array
    {
        $operations = [];
        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            /** @var PathItem $pathItem */
            foreach (['get' => $pathItem->getGet(), 'post' => $pathItem->getPost(), 'put' => $pathItem->getPut(), 'patch' => $pathItem->getPatch(), 'delete' => $pathItem->getDelete()] as $method => $operation) {
                if ($operation instanceof Operation) {
                    $operations[] = strtoupper($method) . ' ' . $path;
                }
            }
        }
        sort($operations);

        return $operations;
    }

    public function testChaqueRouteDeControleurEstDocumentee(): void
    {
        $this->assertSame(self::controllerApiRoutes(), self::documentedOperations(self::openApi()));
    }

    public function testLeJetonDAgentEstDeclareSansEcraserLeJwt(): void
    {
        $schemes = self::openApi()->getComponents()->getSecuritySchemes();

        $this->assertArrayHasKey('JWT', $schemes);
        $this->assertSame('bearer', $schemes[CustomRoutesOpenApiFactory::AGENT_TOKEN_SCHEME]['scheme']);
    }

    public function testLaSessionDeLAgentExigeLeJetonDAgent(): void
    {
        $session = self::openApi()->getPaths()->getPath('/api/agent/v1/session')->getGet();

        $this->assertSame([[CustomRoutesOpenApiFactory::AGENT_TOKEN_SCHEME => []]], $session->getSecurity());
    }

    public function testLesRoutesPubliquesNeDemandentAucuneAuthentification(): void
    {
        foreach (['/api/auth/discord', '/api/agent/v1/device', '/api/agent/v1/token'] as $path) {
            $this->assertSame([], self::openApi()->getPaths()->getPath($path)->getPost()->getSecurity(), $path);
        }
    }

    public function testChaqueReferenceDeSchemaExiste(): void
    {
        $openApi = self::openApi();
        preg_match_all('~#/components/schemas/([A-Za-z]+)~', self::serialize($openApi), $references);

        $this->assertNotEmpty($references[1]);
        foreach (array_unique($references[1]) as $schemaName) {
            $this->assertArrayHasKey($schemaName, $openApi->getComponents()->getSchemas(), $schemaName);
        }
    }

    private static function serialize(OpenApi $openApi): string
    {
        $describe = static function (mixed $value) use (&$describe): mixed {
            if ($value instanceof \ArrayObject) {
                return array_map($describe, $value->getArrayCopy());
            }
            if (is_array($value)) {
                return array_map($describe, $value);
            }
            if (is_object($value)) {
                $properties = [];
                foreach ((new \ReflectionObject($value))->getProperties() as $property) {
                    $properties[$property->getName()] = $describe($property->getValue($value));
                }

                return $properties;
            }

            return $value;
        };

        return json_encode($describe([$openApi->getPaths()->getPaths(), $openApi->getComponents()->getSchemas()]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
