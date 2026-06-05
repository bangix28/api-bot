# Architecture Hexagonale en PHP/Symfony

## Sommaire

1. [Le problème](#1-le-problème)
2. [Les 3 zones](#2-les-3-zones)
3. [Le Domain](#3-le-domain)
4. [Les Ports](#4-les-ports-interfaces)
5. [Le Use Case](#5-le-use-case-application)
6. [Les Adapters](#6-les-adapters-infrastructure)
7. [Le Controller](#7-le-controller)
8. [Configuration Symfony](#8-configuration-symfony)
9. [Tests unitaires](#9-tests-unitaires)
10. [Sources](#10-sources)

---

## 1. Le problème

Un controller classique fait tout à la fois :

```php
// ❌ Fat Controller — tout mélangé
class RegistrationController
{
    public function register(Request $request, EntityManagerInterface $em): Response
    {
        $user = new User();
        $user->setPassword($hasher->hashPassword($user, $plainPassword));
        $em->persist($user);
        $em->flush();
    }
}
```

**Problèmes :**
- Impossible à tester sans base de données
- Couplé à Doctrine, Symfony — changer d'ORM = tout réécrire
- La logique métier est noyée dans l'infrastructure

---

## 2. Les 3 zones

```
┌─────────────────────────────────────────────┐
│              INFRASTRUCTURE                  │  Symfony, Doctrine, HTTP
│  ┌───────────────────────────────────────┐  │
│  │           APPLICATION                 │  │  Use Cases (orchestration)
│  │  ┌─────────────────────────────────┐  │  │
│  │  │           DOMAIN                │  │  │  Règles métier pures
│  │  └─────────────────────────────────┘  │  │
│  └───────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
```

**Règle fondamentale :** les dépendances ne vont que vers l'intérieur. Le Domain ne connaît ni Symfony, ni Doctrine.

---

## 3. Le Domain

### Entité Domain

Représente un objet métier **pur**, sans aucune dépendance Symfony/Doctrine.

```php
// ✅ UserEntity — zéro import externe
class UserEntity
{
    public function __construct(
        private UserEmail $email,
        private string    $password,
        private array     $roles
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->password)) {
            throw new \InvalidArgumentException('Password cannot be empty');
        }
        if (count($this->roles) === 0) {
            throw new \InvalidArgumentException('User must have at least one role');
        }
    }

    // Immutabilité — withX() au lieu de setX()
    public function withEmail(UserEmail $email): self
    {
        return new static($email, $this->password, $this->roles);
    }

    public function getEmail(): UserEmail { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function getRoles(): array { return $this->roles; }
}
```

### Value Object

Un objet qui **représente une valeur métier valide**. Pas d'ID, immuable, auto-validant.

```php
// ✅ UserEmail — impossible de créer un email invalide
class UserEmail
{
    public function __construct(private string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
```

**Principe :** *"Make invalid states unrepresentable"* — un état invalide ne peut pas exister.

### Immutabilité

Au lieu de modifier l'objet existant, on retourne une **nouvelle instance** :

```php
// ❌ Setter — l'objet peut devenir invalide
$user->setEmail("pas_un_email");

// ✅ With — toujours valide, validation rejouée
$newUser = $user->withEmail(new UserEmail("john@example.com"));
```

---

## 4. Les Ports (interfaces)

Les ports définissent **ce dont le Domain a besoin**, sans savoir comment c'est implémenté.

```php
// Port de sortie — le Domain définit le contrat
interface UserRepositoryInterface
{
    public function save(UserEntity $user): void;
    public function findByEmail(UserEmail $email): ?UserEntity;
}

interface PasswordHasherInterface
{
    public function hash(string $plainPassword): string;
}
```

**Principe de dépendance inversée (SOLID - D) :**
```
❌ Handler → DoctrineUserRepository  (couplage direct)
✅ Handler → UserRepositoryInterface (inversion)
                    ↑
        DoctrineUserRepository (s'adapte au contrat)
```

---

## 5. Le Use Case (Application)

### Command

DTO d'entrée immuable — transporte les données brutes de l'extérieur.

```php
readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
```

> **CQRS** : `Command` = intention de modifier l'état (retourne `void`).
> `Query` = intention de lire l'état (retourne des données).

### Handler

Orchestre le use case. Dépend uniquement des interfaces du Domain.

```php
class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher
    ) {}

    public function handle(RegisterUserCommand $command): void
    {
        $userEmail = new UserEmail($command->email);

        if ($this->userRepository->findByEmail($userEmail)) {
            throw new UserAlreadyExistsException();
        }

        $hashedPassword = $this->passwordHasher->hash($command->password);
        $userEntity = new UserEntity($userEmail, $hashedPassword, ['ROLE_USER']);

        $this->userRepository->save($userEntity);
    }
}
```

**Avantage clé :** testable sans Symfony, sans Doctrine, en millisecondes.

---

## 6. Les Adapters (Infrastructure)

Implémentations concrètes des ports. Ils vivent dans l'Infrastructure.

### DoctrineUserRepository

```php
class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function save(UserEntity $user): void
    {
        // Mapping Domain → Doctrine
        $doctrineUser = new User();
        $doctrineUser->setEmail($user->getEmail()->getValue());
        $doctrineUser->setPassword($user->getPassword());
        $doctrineUser->setRoles($user->getRoles());

        $this->entityManager->persist($doctrineUser);
        $this->entityManager->flush();
    }

    public function findByEmail(UserEmail $email): ?UserEntity
    {
        $doctrineUser = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email->getValue()]);

        if ($doctrineUser === null) return null;

        // Mapping Doctrine → Domain
        return new UserEntity(
            new UserEmail($doctrineUser->getEmail()),
            $doctrineUser->getPassword(),
            $doctrineUser->getRoles()
        );
    }
}
```

### SymfonyPasswordHasher

```php
use Symfony\Component\PasswordHasher\PasswordHasherInterface as SymfonyHasher;

class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(private SymfonyHasher $symfonyHasher) {}

    public function hash(string $password): string
    {
        return $this->symfonyHasher->hash($password);
    }
}
```

---

## 7. Le Controller

Ultra-fin : traduit HTTP → Use Case, rien de plus.

```php
class RegistrationController extends AbstractController
{
    public function __construct(private RegisterUserHandler $handler) {}

    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->handler->handle(new RegisterUserCommand(
                    $user->getEmail(),
                    $form->get('plainPassword')->getData()
                ));
                return $this->redirectToRoute('admin');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
```

---

## 8. Configuration Symfony

Symfony doit savoir quelle implémentation concrète injecter pour chaque interface :

```yaml
# config/services.yaml
services:
    # Lier les interfaces à leurs implémentations
    App\Domain\User\UserRepositoryInterface:
        alias: App\Infrastructure\User\DoctrineUserRepository

    App\Domain\User\PasswordHasherInterface:
        alias: App\Infrastructure\User\SymfonyPasswordHasher

    # Déclarer NativePasswordHasher comme service
    Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher: ~

    Symfony\Component\PasswordHasher\PasswordHasherInterface:
        alias: Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher
```

---

## 9. Tests unitaires

L'archi hexagonale permet de tester le Use Case **sans infrastructure**.

### Faux objets (Test Doubles)

```php
// InMemoryUserRepository — simule la BDD avec un array
class InMemoryUserRepository implements UserRepositoryInterface
{
    private array $users = [];

    public function save(UserEntity $user): void
    {
        $this->users[$user->getEmail()->getValue()] = $user;
    }

    public function findByEmail(UserEmail $email): ?UserEntity
    {
        return $this->users[$email->getValue()] ?? null;
    }
}

// FakePasswordHasher — résultat prévisible pour les assertions
class FakePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $password): string
    {
        return 'hashed_' . $password;
    }
}
```

### Les tests

```php
class RegisterUserHandlerTest extends TestCase
{
    // ✅ Cas nominal
    public function testRegisterUserSuccessfully(): void
    {
        $repository = new InMemoryUserRepository();
        $handler = new RegisterUserHandler($repository, new FakePasswordHasher());

        $handler->handle(new RegisterUserCommand('john@example.com', 'password123'));

        $user = $repository->findByEmail(new UserEmail('john@example.com'));
        $this->assertNotNull($user);
        $this->assertEquals('hashed_password123', $user->getPassword());
    }

    // ✅ Cas d'erreur
    public function testRegisterUserWithExistingEmailThrowsException(): void
    {
        $repository = new InMemoryUserRepository();
        $handler = new RegisterUserHandler($repository, new FakePasswordHasher());

        // Pré-remplir le repository
        $repository->save(new UserEntity(
            new UserEmail('john@example.com'), 'hashed_pass', ['ROLE_USER']
        ));

        $this->expectException(UserAlreadyExistsException::class);
        $handler->handle(new RegisterUserCommand('john@example.com', 'password123'));
    }
}
```

### Structure finale

```
src/
├── Domain/User/
│   ├── UserEntity.php              ← Entité métier pure
│   ├── UserEmail.php               ← Value Object
│   ├── UserRepositoryInterface.php ← Port de sortie
│   ├── PasswordHasherInterface.php ← Port de sortie
│   └── UserAlreadyExistsException.php
│
├── Application/User/Register/
│   ├── RegisterUserCommand.php     ← DTO d'entrée
│   └── RegisterUserHandler.php     ← Use Case
│
└── Infrastructure/User/
    ├── DoctrineUserRepository.php  ← Adapter Doctrine
    └── SymfonyPasswordHasher.php   ← Adapter Symfony
```

---

## 10. Sources

### Architecture Hexagonale
- [Hexagonal Architecture — Alistair Cockburn (inventeur du pattern)](https://alistair.cockburn.us/hexagonal-architecture/)
- [Ports and Adapters — Wikipedia](https://en.wikipedia.org/wiki/Hexagonal_architecture_(software))

### DDD & Value Objects
- [Value Objects — Martin Fowler](https://martinfowler.com/bliki/ValueObject.html)
- [Make invalid states unrepresentable](https://ybogomolov.me/making-illegal-states-unrepresentable)

### SOLID
- [Dependency Inversion Principle](https://en.wikipedia.org/wiki/Dependency_inversion_principle)
- [SOLID en PHP](https://matthiasnoback.nl/2018/08/beyond-clean-code/)

### CQRS
- [CQRS — Martin Fowler](https://martinfowler.com/bliki/CQRS.html)

### Symfony
- [Service Container & Aliases](https://symfony.com/doc/current/service_container/alias_private.html)
- [Dependency Injection](https://symfony.com/doc/current/service_container.html)
- [Password Hashing](https://symfony.com/doc/current/security/passwords.html)
- [Flash Messages](https://symfony.com/doc/current/controller.html#flash-messages)

### Tests
- [PHPUnit — Assertions](https://docs.phpunit.de/en/11.0/assertions.html)
- [PHPUnit — Testing Exceptions](https://docs.phpunit.de/en/11.0/writing-tests-for-phpunit.html#testing-exceptions)
- [Test Doubles](https://docs.phpunit.de/en/11.0/test-doubles.html)

### Pour aller plus loin
- [Advanced Web Application Architecture — Matthias Noback](https://matthiasnoback.nl/book/advanced-web-application-architecture/)
- [Domain-Driven Design — Eric Evans](https://www.domainlanguage.com/ddd/)
