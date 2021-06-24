SHELL := /bin/bash

YARN := $(shell command -v yarn 2> /dev/null)
NODE_PREFIX=$(shell pwd)
COMPOSER_BIN := $(shell command -v composer 2> /dev/null)
ifndef COMPOSER_BIN
    $(error composer is not available on your system, please install composer)
endif

NPM := $(shell command -v npm 2> /dev/null)
ifndef NPM
    $(error npm is not available on your system, please install npm)
endif

app_name=qnap

# bin file definitions
PHPUNIT=php -d zend.enable_gc=0  "$(PWD)/../../lib/composer/bin/phpunit"
PHPUNITDBG=phpdbg -qrr -d memory_limit=4096M -d zend.enable_gc=0 "$(PWD)/../../lib/composer/bin/phpunit"
PHP_CS_FIXER=php -d zend.enable_gc=0 vendor-bin/owncloud-codestyle/vendor/bin/php-cs-fixer
PHAN=php -d zend.enable_gc=0 vendor-bin/phan/vendor/bin/phan
PHPSTAN=php -d zend.enable_gc=0 vendor-bin/phpstan/vendor/bin/phpstan

KARMA=$(NODE_PREFIX)/node_modules/.bin/karma

.DEFAULT_GOAL := all

help:
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | sed -e 's/  */ /' | column -t -s :

##
## Entrypoints
##----------------------

.PHONY: all
all: install-deps

# Remove the appstore build
.PHONY: clean
clean: clean-nodejs-deps clean-composer-deps
	rm -rf ./build

.PHONY: clean-nodejs-deps
clean-nodejs-deps:
	rm -Rf $(nodejs_deps)

.PHONY: clean-composer-deps
clean-composer-deps:
	rm -rf ./vendor
	rm -Rf vendor-bin/**/vendor vendor-bin/**/composer.lock

.PHONY: dev
dev: ## Initialize dev environment
dev: install-deps

#
# Release
# make this app compatible with the ownCloud
# default build tools
#
.PHONY: dist
dist:
	make -f Makefile.release dist

$(KARMA): $(nodejs_deps)

##
## Tests
##----------------------

.PHONY: test-php-unit
test-php-unit: ## Run php unit tests
test-php-unit: vendor/bin/phpunit
	$(PHPUNIT) --configuration ./phpunit.xml --testsuite qnap-unit

.PHONY: test-php-unit-dbg
test-php-unit-dbg: ## Run php unit tests using phpdbg
test-php-unit-dbg: vendor/bin/phpunit
	$(PHPUNITDBG) --configuration ./phpunit.xml --testsuite qnap-unit

.PHONY: test-php-style
test-php-style: ## Run php-cs-fixer and check owncloud code-style
test-php-style: vendor-bin/owncloud-codestyle/vendor
	$(PHP_CS_FIXER) fix -v --diff --diff-format udiff --allow-risky yes --dry-run

.PHONY: test-php-style-fix
test-php-style-fix: ## Run php-cs-fixer and fix code style issues
test-php-style-fix: vendor-bin/owncloud-codestyle/vendor
	$(PHP_CS_FIXER) fix -v --diff --diff-format udiff --allow-risky yes

.PHONY: test-php-phan
test-php-phan: ## Run phan
test-php-phan: vendor-bin/phan/vendor
	$(PHAN) --config-file .phan/config.php --require-config-exists

.PHONY: test-php-phpstan
test-php-phpstan: ## Run phpstan
test-php-phpstan: vendor-bin/phpstan/vendor
	$(PHPSTAN) analyse --memory-limit=4G --configuration=./phpstan.neon --no-progress --level=5 appinfo lib

.PHONY: test-js
test-js: $(nodejs_deps)
	$(KARMA) start tests/js/karma.config.js --single-run

#
# Translation
#--------------------------------------

.PHONY: l10n-push
l10n-push:
	cd l10n && tx -d push -s --skip --no-interactive

.PHONY: l10n-pull
l10n-pull:
	cd l10n && tx -d pull -a --skip --minimum-perc=75

.PHONY: l10n-clean
l10n-clean:
	rm -rf l10n/l10n.pl
	find l10n -type f -name \*.po -or -name \*.pot | xargs rm -f
	find l10n -type f -name uz.\* -or -name yo.\* -or -name ne.\* -or -name or_IN.\* | xargs git rm -f || true

.PHONY: l10n-read
l10n-read: l10n/l10n.pl
	cd l10n && perl l10n.pl $(app_name) read

.PHONY: l10n-write
l10n-write: l10n/l10n.pl
	cd l10n && perl l10n.pl $(app_name) write

l10n/l10n.pl:
	wget -qO l10n/l10n.pl https://raw.githubusercontent.com/owncloud-ci/transifex/d1c63674d791fe8812216b29da9d8f2f26e7e138/rootfs/usr/bin/l10n

##
## Dependency management
##----------------------

.PHONY: install-deps
install-deps: ## Install dependencies
install-deps: install-php-deps install-js-deps

composer.lock: composer.json
	@echo composer.lock is not up to date.

.PHONY: install-php-deps
install-php-deps: ## Install PHP dependencies
install-php-deps: vendor vendor-bin composer.json composer.lock

.PHONY: install-js-deps
install-js-deps: ## Install PHP dependencies
install-js-deps: $(nodejs_deps)

vendor: composer.lock
	$(COMPOSER_BIN) install --no-dev

vendor/bin/phpunit: composer.lock
	$(COMPOSER_BIN) install

vendor/bamarni/composer-bin-plugin: composer.lock
	$(COMPOSER_BIN) install

vendor-bin/owncloud-codestyle/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/owncloud-codestyle/composer.lock
	$(COMPOSER_BIN) bin owncloud-codestyle install --no-progress

vendor-bin/owncloud-codestyle/composer.lock: vendor-bin/owncloud-codestyle/composer.json
	@echo owncloud-codestyle composer.lock is not up to date.

vendor-bin/phan/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/phan/composer.lock
	$(COMPOSER_BIN) bin phan install --no-progress

vendor-bin/phan/composer.lock: vendor-bin/phan/composer.json
	@echo phan composer.lock is not up to date.

vendor-bin/phpstan/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/phpstan/composer.lock
	$(COMPOSER_BIN) bin phpstan install --no-progress

vendor-bin/phpstan/composer.lock: vendor-bin/phpstan/composer.json
	@echo phpstan composer.lock is not up to date.
