<?php

namespace App\Tests\Infrastructure\Security;

use App\Infrastructure\Security\PlayerUser;
use App\Infrastructure\Security\PlayerUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class PlayerUserProviderTest extends TestCase
{
    private PlayerUserProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new PlayerUserProvider();
    }

    public function testUnJoueurEstReconstruitDepuisSonJeton(): void
    {
        $player = $this->provider->loadUserByIdentifierAndPayload('discord:123', [PlayerUserProvider::USERNAME_CLAIM => 'pseudo']);

        $this->assertInstanceOf(PlayerUser::class, $player);
        $this->assertSame('discord:123', $player->getUserIdentifier());
        $this->assertSame('pseudo', $player->discord->username);
        $this->assertSame([PlayerUser::ROLE], $player->getRoles());
    }

    public function testUnIdentifiantEmailEstLaisseAuxComptesDAdministration(): void
    {
        // Dans la chaîne de fournisseurs, UserNotFound fait passer au suivant.
        $this->expectException(UserNotFoundException::class);

        $this->provider->loadUserByIdentifierAndPayload('admin@api-bot.local', [PlayerUserProvider::USERNAME_CLAIM => 'pseudo']);
    }

    public function testSeulLePrefixeDiscordDesigneUnJoueur(): void
    {
        // Même longueur de préfixe et un identifiant numérique derrière : seul
        // le préfixe distingue un joueur Discord d'un autre type d'identifiant.
        $this->expectException(UserNotFoundException::class);

        $this->provider->loadUserByIdentifierAndPayload('steamid:123', [PlayerUserProvider::USERNAME_CLAIM => 'pseudo']);
    }

    public function testUnJetonSansNomDiscordEstRefuse(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->provider->loadUserByIdentifierAndPayload('discord:123', []);
    }

    public function testUnIdentifiantDiscordInvalideEstRefuse(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->provider->loadUserByIdentifierAndPayload('discord:pas-un-nombre', [PlayerUserProvider::USERNAME_CLAIM => 'pseudo']);
    }

    public function testUnJoueurNeSeChargePasSansSonJeton(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->provider->loadUserByIdentifier('discord:123');
    }

    public function testSeulsLesJoueursSontPrisEnCharge(): void
    {
        $this->assertTrue($this->provider->supportsClass(PlayerUser::class));
        $this->assertFalse($this->provider->supportsClass(\App\Entity\User::class));
    }
}
