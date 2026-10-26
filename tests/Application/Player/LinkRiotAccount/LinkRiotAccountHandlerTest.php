<?php

namespace App\Tests\Application\Player\LinkRiotAccount;

use App\Application\Player\LinkRiotAccount\LinkRiotAccountCommand;
use App\Application\Player\LinkRiotAccount\LinkRiotAccountHandler;
use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\InvalidRiotIdException;
use App\Domain\Player\RiotAccountAlreadyLinkedException;
use App\Domain\Player\RiotAccountNotTrackedException;
use App\Domain\Player\TrackedRiotAccount;
use App\Tests\Domain\Player\InMemoryTrackedRiotAccountRepository;
use PHPUnit\Framework\TestCase;

class LinkRiotAccountHandlerTest extends TestCase
{
    private InMemoryTrackedRiotAccountRepository $accounts;
    private DiscordIdentity $player;

    protected function setUp(): void
    {
        $this->accounts = (new InMemoryTrackedRiotAccountRepository())
            ->add(new TrackedRiotAccount('Pseudo#EUW', 'puuid-libre', null))
            ->add(new TrackedRiotAccount('Autre#EUW', 'puuid-pris', '999'));
        $this->player = new DiscordIdentity('123', 'pseudo');
    }

    private function link(string $riotId): TrackedRiotAccount
    {
        return (new LinkRiotAccountHandler($this->accounts))->handle(new LinkRiotAccountCommand($this->player, $riotId));
    }

    public function testUnCompteSuiviEtLibreEstLieAuJoueur(): void
    {
        $linked = $this->link('Pseudo#EUW');

        $this->assertSame('123', $linked->ownerDiscordId);
        $this->assertSame(['Pseudo#EUW'], array_map(
            static fn(TrackedRiotAccount $account) => $account->riotId,
            $this->accounts->findOwnedBy('123'),
        ));
    }

    public function testLeNomDiscordEstRetenuPourLAdministration(): void
    {
        $this->link('Pseudo#EUW');

        $this->assertSame('pseudo', $this->accounts->linkedUsernames['puuid-libre']);
    }

    public function testLaCasseDuRiotIdNImportePas(): void
    {
        $this->assertSame('Pseudo#EUW', $this->link('pseudo#euw')->riotId);
    }

    public function testLierDeuxFoisLeMemeCompteNeChangeRien(): void
    {
        $this->link('Pseudo#EUW');

        $this->assertSame('123', $this->link('Pseudo#EUW')->ownerDiscordId);
    }

    public function testUnCompteNonSuiviNePeutPasEtreLie(): void
    {
        $this->expectException(RiotAccountNotTrackedException::class);

        $this->link('Inconnu#EUW');
    }

    public function testUnCompteDejaLieAUnAutreJoueurEstRefuse(): void
    {
        $this->expectException(RiotAccountAlreadyLinkedException::class);

        $this->link('Autre#EUW');
    }

    public function testUnRiotIdMalFormeEstRefuseAvantToutAccesAuxComptes(): void
    {
        $this->expectException(InvalidRiotIdException::class);

        $this->link('Pseudo');
    }
}
