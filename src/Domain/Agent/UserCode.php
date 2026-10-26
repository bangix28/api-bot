<?php

namespace App\Domain\Agent;

/**
 * Code d'appairage affiché par l'agent et saisi par le joueur sur le front.
 *
 * 8 caractères d'un alphabet sans ambiguïté (ni 0/O, ni 1/I/L) ; affiché en
 * deux groupes « K7QF-92PZ », accepté quels que soient la casse, les espaces
 * et le tiret à la saisie.
 */
final readonly class UserCode
{
    public const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const int LENGTH = 8;

    /** Forme canonique : 8 caractères en majuscules, sans séparateur. */
    public string $value;

    public function __construct(string $typed)
    {
        $normalized = strtoupper(str_replace(['-', ' '], '', trim($typed)));

        // strspn ne compte que les caractères valides en tête de chaîne : la
        // longueur se vérifie à part (« K7QF92PZ! » commence par 8 caractères valides).
        if (strspn($normalized, self::ALPHABET) !== self::LENGTH || strlen($normalized) !== self::LENGTH) {
            throw new InvalidUserCodeException('Ce code n\'a pas le format d\'un code d\'appairage.');
        }

        $this->value = $normalized;
    }

    public function display(): string
    {
        return substr($this->value, 0, 4) . '-' . substr($this->value, 4);
    }
}
