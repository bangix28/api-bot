# Environnement de dev local (Docker)

> La prod tourne **sans Docker** sur un VPS : voir `DEPLOY.md`.

## Installation

```bash
make .env                 # crée .env depuis .env.example, avec APP_SECRET et JWT_PASSPHRASE générés
# éditer .env : RIOT_API_TOKEN, et NGINX_PORT si le port 80 est déjà pris sur le poste
make setup                # build, up, composer, clés JWT, base, assets
```

`make setup` est rejouable : il ne réécrase jamais un `.env` existant, ne régénère
pas les clés JWT et, sur une base déjà initialisée, se contente de jouer les
migrations en attente.

| Service | URL |
|---|---|
| App / admin | `http://localhost:<NGINX_PORT>/admin` |
| API Platform | `http://localhost:<NGINX_PORT>/api` |
| phpMyAdmin | `http://localhost:8080` (serveur `mysql`, `root`, sans mot de passe) |

`/` répond 404 : aucune route n'est définie à la racine.

## Comptes de connexion

`make setup` charge `UserFixtures` (rejouable seule avec `make users`) :

| Email | Mot de passe | Rôles | Usage |
|---|---|---|---|
| `admin@api-bot.local` | `admin` | `ROLE_ADMIN`, `ROLE_API` | admin (`/login`), `/refresh`, toute l'API |
| `api@api-bot.local` | `api` | `ROLE_API` | API seule, comme le front |

Token JWT pour l'API :
```bash
curl -s -X POST localhost:<NGINX_PORT>/api/login -H 'Content-Type: application/json' \
  -d '{"email":"api@api-bot.local","password":"api"}'
```

Jeu de données factice de la Ranked Race (comptes et événements marqués `FIXTURE`) :
```bash
make console cmd="doctrine:fixtures:load --group=RankedRaceDatasetFixtures --append"
```

> ⚠️ Toujours passer `--append` à `doctrine:fixtures:load` : sans lui, la commande
> **purge toute la base**.

## Données Riot

Aucun cron ne tourne en local. Après avoir renseigné `RIOT_API_TOKEN` et ajouté des
comptes dans l'admin (Riot ID au format `Pseudo#TAG`) :
```bash
make console cmd=refreshSummoners   # rangs, historique, points de la Ranked Race
make console cmd=daily-elo          # courbe /elo-daily
```

## Base de données : pourquoi pas seulement les migrations

L'historique de migrations ne contient pas le schéma initial : la première
(`Version20250926211832`) fait un `ALTER` sur `history_account_lol`, qu'aucune
migration ne crée. `make db-init` gère donc deux cas :

- **base vide** → `doctrine:schema:create` depuis les entités, puis
  `doctrine:migrations:version --add --all` pour marquer toutes les migrations
  comme jouées ;
- **base existante** → `doctrine:migrations:migrate`.

Les migrations de reprise de données sont ainsi sautées sur une base neuve, ce
qui est sans effet puisqu'elle est vide.

## Cibles utiles

| Cible | Rôle |
|---|---|
| `make up` / `make down` | démarrer / arrêter les conteneurs |
| `make build` | reconstruire l'image `api-bot` |
| `make db-init` | créer et mettre à jour la base |
| `make users` | créer / réinitialiser les comptes de connexion de dev |
| `make assets` | `npm install` + build Encore (dev) |
| `make console cmd="..."` | lancer une commande Symfony |
| `make sync-vendor` | copier `vendor/` du conteneur vers l'hôte (autocomplétion IDE) |
