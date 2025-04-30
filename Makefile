FONT_RED := $(shell tput setaf 1)
FONT_GREEN := $(shell tput setaf 2)
FONT_YELLOW := $(shell tput setaf 3)
FONT_RESET := $(shell tput sgr0)

DOCKER_COMPOSE := docker compose

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
	@$(DOCKER_COMPOSE) exec app php ./vendor/bin/phpunit
