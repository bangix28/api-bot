# CLAUDE.md — api-bot

## Stack

- **PHP 8.2+** (runtime 8.4), Symfony 8.0
- Doctrine ORM 3.0 + Migrations
- API Platform v4 (REST/JSON-LD)
- Lexik JWT Authentication
- EasyAdmin 5
- PHPUnit 12.4
- Webpack Encore (frontend assets)

> Le projet tourne dans Docker (conteneur `api` pour PHP-FPM 8.4, `mysql`, `nginx`, `phpmyadmin`).
> Lancer les commandes via `docker exec api php bin/console ...`. Base MySQL : `apibot` (root, mot de passe vide).

## Architecture — Hexagonal (Ports & Adapters)

```
src/
├── Domain/        # Pure business logic — no framework dependencies
├── Application/   # Use cases (Command + Handler pattern)
├── Infrastructure/# Adapters: Doctrine, Symfony services
├── Entity/        # Doctrine ORM entities (separate from domain models)
├── Controller/    # HTTP adapters (thin — delegate to handlers)
├── Repository/    # Doctrine repositories (for ORM entities)
└── Services/      # Application services (Riot API, etc.)
```

### Rules

- **Domain** must never import from Symfony, Doctrine, or any framework.
- **Application** depends only on Domain interfaces (ports), never on concrete infra.
- **Infrastructure** implements domain interfaces (e.g. `UserRepositoryInterface`, `PasswordHasherInterface`).
- **Controllers** are thin: build a Command, call the Handler, handle the response. No business logic.
- Domain `UserEntity` and Doctrine `Entity\User` are separate classes. The infra layer converts between them.

## Common Commands

```bash
# Run tests
php bin/phpunit

# Symfony console
php bin/console <command>

# Database migrations
php bin/console doctrine:migrations:migrate

# Clear cache
php bin/console cache:clear
```

## Adding a New Use Case

1. Create `src/Domain/<Aggregate>/` — value objects, interfaces, exceptions
2. Create `src/Application/<Aggregate>/<UseCase>/` — `XxxCommand.php` + `XxxHandler.php`
3. Implement infra adapters in `src/Infrastructure/<Aggregate>/`
4. Write unit tests in `tests/Application/<Aggregate>/<UseCase>/` with in-memory test doubles
5. Wire the controller (or CLI command) as a thin adapter

## Testing

- Unit tests use **in-memory test doubles** (see `tests/Domain/User/InMemoryUserRepository.php`)
- No mocking of the database in unit tests — use in-memory implementations of domain interfaces
- Test doubles live under `tests/Domain/` and implement domain interfaces
- PHPUnit is strict: deprecations, notices, and warnings are treated as failures

## Key Domain Concepts

| Concept | Class | Notes |
|---|---|---|
| User aggregate | `Domain\User\UserEntity` | Immutable — use `withXxx()` to derive new instances |
| Email value object | `Domain\User\UserEmail` | Validates format on construction |
| Repository port | `Domain\User\UserRepositoryInterface` | Implemented by `DoctrineUserRepository` |
| Password hasher port | `Domain\User\PasswordHasherInterface` | Implemented by `SymfonyPasswordHasher` |
| Registration use case | `Application\User\Register\RegisterUserHandler` | Checks duplicate, hashes, persists |
