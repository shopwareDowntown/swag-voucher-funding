# VARIABLES
USER_ID := $(shell id -u)
GROUP_ID := $(shell id -g)

# TARGETS
.PHONY: help test ecs-dry ecs-fix

.DEFAULT_GOAL := help

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

test: ## runs phpunit
	composer dump-autoload
	php -d pcov.enabled=1 -d pcov.directory=./src ../../../vendor/bin/phpunit \
       --configuration phpunit.xml.dist \
       --coverage-clover build/artifacts/phpunit.clover.xml \
       --coverage-html build/artifacts/phpunit-coverage-html

install-ecs:
ifeq (,$(wildcard ./dev-ops/tools/ecs/vendor/.))
	composer install -d dev-ops/tools/ecs
endif

ecs-dry: | install-ecs  ## runs easy coding standard in dry mode
	dev-ops/tools/ecs/vendor/bin/ecs check . --config dev-ops/tools/ecs/easy-coding-standard.yml

ecs-fix: | install-ecs  ## runs easy coding standard and fixes issues
	dev-ops/tools/ecs/vendor/bin/ecs check . --fix --config dev-ops/tools/ecs/easy-coding-standard.yml
