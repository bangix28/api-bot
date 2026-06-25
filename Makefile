.PHONY: up down build install require sync-vendor console

up: ## Start containers in background
	docker compose up -d

down: ## Stop containers
	docker compose down

build: ## Rebuild the api image
	docker compose build api

install: ## Run composer install inside the container
	docker compose exec api composer install

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
