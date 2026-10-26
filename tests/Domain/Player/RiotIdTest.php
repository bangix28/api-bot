<?php

namespace App\Tests\Domain\Player;

use App\Domain\Player\InvalidRiotIdException;
use App\Domain\Player\RiotId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RiotIdTest extends TestCase
{
    public function testLesEspacesAutourSontRetires(): void
    {
        $this->assertSame('Pseudo#EUW', (new RiotId('  Pseudo#EUW '))->value);
    }

    public function testUnPseudoAvecEspacesEstAccepte(): void
    {
        // Les Riot ID peuvent contenir des espaces (« BCTG sHoTeUr#4861 »).
        $this->assertSame('BCTG Joueur#4861', (new RiotId('BCTG Joueur#4861'))->value);
    }

    /** @return iterable<string, array{string}> */
    public static function malformed(): iterable
    {
        yield 'sans tag' => ['Pseudo'];
        yield 'tag vide' => ['Pseudo#'];
        yield 'pseudo vide' => ['#EUW'];
        yield 'deux dièses' => ['Pseu#do#EUW'];
        yield 'vide' => [''];
    }

    #[DataProvider('malformed')]
    public function testUnRiotIdMalFormeEstRefuse(string $input): void
    {
        $this->expectException(InvalidRiotIdException::class);

        new RiotId($input);
    }
}
