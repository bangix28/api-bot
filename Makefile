.PHONY: setup up down build install wait-db db-init users assets require sync-vendor console

DB_NAME ?= apibot

setup: | .env ## Install the whole local environment (safe to re-run)
	$(MAKE) build up wait-db
	docker compose exec -T api composer install --no-interaction
	docker compose exec -T api php bin/console lexik:jwt:generate-keypair --skip-if-exists
	$(MAKE) db-init users assets

.env: ## Create .env from .env.example with fresh secrets (never overwrites an existing .env)
	cp .env.example .env
	sed -i "s/^APP_SECRET=.*/APP_SECRET=$$(openssl rand -hex 16)/" .env
	sed -i "s/^JWT_PASSPHRASE=.*/JWT_PASSPHRASE=$$(openssl rand -hex 16)/" .env

up: ## Start containers in background
	docker compose up -d

down: ## Stop containers
	docker compose down

build: ## Rebuild the api image
	docker compose build api

install: ## Run composer install inside the container
	docker compose exec api composer install

wait-db: ## Wait until MySQL accepts connections (60 s max)
	@for i in $$(seq 1 30); do \
		docker compose exec -T mysql mysqladmin ping -h127.0.0.1 --silent 2>/dev/null && exit 0; \
		sleep 2; \
	done; \
	echo "MySQL ne répond pas après 60 s" >&2; exit 1

# Les migrations ne contiennent pas le schéma initial (la première fait un ALTER
# sur une table qu'aucune migration ne crée) : sur une base vide, on crée le
# schéma depuis les entités et on marque toutes les migrations comme jouées.
db-init: ## Create the database; empty DB → schema from entities, otherwise run migrations
	docker compose exec -T api php bin/console doctrine:database:create --if-not-exists
	@tables=$$(docker compose exec -T mysql mysql -uroot -N -e \
		"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$(DB_NAME)' AND table_name <> 'doctrine_migration_versions'"); \
	if [ "$$tables" = "0" ]; then \
		echo "Base vide : création du schéma depuis les entités"; \
		docker compose exec -T api php bin/console doctrine:schema:create && \
		docker compose exec -T api php bin/console doctrine:migrations:sync-metadata-storage && \
		docker compose exec -T api php bin/console doctrine:migrations:version --add --all --no-interaction; \
	else \
		docker compose exec -T api php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; \
	fi

# --append est indispensable : sans lui, doctrine:fixtures:load purge toute la base.
users: ## Create or reset the dev login accounts (admin@api-bot.local / admin, api@api-bot.local / api)
	docker compose exec -T api php bin/console doctrine:fixtures:load --group=user --append --no-interaction

assets: ## Install npm packages and build front assets (dev)
	docker compose exec -T api sh -c "npm install --no-audit --no-fund && npm run dev"

require: ## Add a package and sync vendor to host — usage: make require pkg=vendor/package
	docker compose exec api composer require $(pkg)
	$(MAKE) sync-vendor

require-dev: ## Add a dev package and sync vendor to host — usage: make require-dev pkg=vendor/package
	docker compose exec api composer require --dev $(pkg)
	$(MAKE) sync-vendor

sync-vendor: ## Copy vendor from container to host (refreshes IDE autocompletion)
	docker compose cp api:/usr/src/api/vendor ./vendor

console: ## Run a Symfony console command — usage: make console cmd="cache:clear"
	docker compose exec api php bin/console $(cmd)
