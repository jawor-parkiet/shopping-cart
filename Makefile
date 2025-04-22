FONT_RED := $(shell tput setaf 1)
FONT_GREEN := $(shell tput setaf 2)
FONT_YELLOW := $(shell tput setaf 3)
FONT_RESET := $(shell tput sgr0)

PATH_ENV := ./src/.env
PATH_ENV_EXAMPLE := ./src/.env.example
PATH_ENV_TESTING := ./src/.env.testing
PATH_CODE_COVERAGE := tests/Report

DOCKER_COMPOSE := docker compose
DOCKER_COMPOSE_TESTING := docker compose --env-file $(PATH_ENV_TESTING)

.DEFAULT_GOAL := help

help:
	@echo "Help"

start:
	@printf "$(FONT_YELLOW)Starting…$(FONT_RESET) \n"
	@$(DOCKER_COMPOSE) up -d

stop:
	@printf "$(FONT_YELLOW)Stopping…$(FONT_RESET) \n"
	@$(DOCKER_COMPOSE) stop

rebuild:
	@printf "$(FONT_YELLOW)Rebuilding…$(FONT_RESET) \n"
	@$(DOCKER_COMPOSE) up -d --build --force-recreate --remove-orphans

reset: stop rebuild
	@rm -rf src/vendor
	@mkdir -p src/vendor
	@$(DOCKER_COMPOSE) exec app composer install

ssh:
	@$(DOCKER_COMPOSE) exec app bash

test:
	@$(DOCKER_COMPOSE_TESTING) exec app php artisan test

quality: quality-pint quality-phpstan

quality-pint:
	@$(DOCKER_COMPOSE) exec app php ./vendor/bin/pint -v --test

quality-phpstan:
	@$(DOCKER_COMPOSE) exec app php ./vendor/bin/phpstan analyse --memory-limit=2G
