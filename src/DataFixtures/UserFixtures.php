<?php

namespace App\DataFixtures;

use App\Domain\User\PasswordHasherInterface;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Comptes de connexion pour le dev local : /register étant réservé à ROLE_ADMIN,
 * une base neuve n'a aucun moyen d'obtenir son premier admin sans cette fixture.
 *
 * À charger avec --group=user --append (cf. `make users`) : sans --append,
 * doctrine:fixtures:load purge toute la base.
 *
 * Rejouable : un compte déjà présent est remis à l'état ci-dessous (rôles et mot
 * de passe) plutôt que supprimé, ce qui préserve son historique rattaché.
 */
class UserFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(private readonly PasswordHasherInterface $passwordHasher)
    {
    }

    public static function getGroups(): array
    {
        return ['user'];
    }

    public function load(ObjectManager $manager): void
    {
        $repository = $manager->getRepository(User::class);

        foreach ($this->accounts() as [$email, $password, $roles]) {
            $user = $repository->findOneBy(['email' => $email]) ?? (new User())->setEmail($email);
            $user->setRoles($roles);
            $user->setPassword($this->passwordHasher->hash($password));
            $manager->persist($user);
        }

        $manager->flush();
    }

    /**
     * @return array{string, string, string[]}[] email, mot de passe en clair, rôles
     */
    private function accounts(): array
    {
        return [
            // Accès à l'admin, à /refresh et à toute l'API
            ['admin@api-bot.local', 'admin', ['ROLE_ADMIN', 'ROLE_API']],
            // Consommateur de l'API seul (le profil du front) : refusé sur /admin
            ['api@api-bot.local', 'api', ['ROLE_API']],
        ];
    }
}
