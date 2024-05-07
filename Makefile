
php-setup:
	docker build -t openfoodfacts-php -f ./docker/Dockerfile .

.PHONY: ci
ci: cs-check phpstan test

.PHONY: cs-check
cs-check:
	docker run  -v ${PWD}/src:/opt/app/src -v ${PWD}/tests:/opt/app/tests  openfoodfacts-php  php ./vendor/bin/php-cs-fixer check

.PHONY: cs-fix
cs-fix:
	docker run  -v ${PWD}/src:/opt/app/src -v ${PWD}/tests:/opt/app/tests  openfoodfacts-php  php ./vendor/bin/php-cs-fixer fix

.PHONY: test
test:
	docker run  -v ${PWD}/src:/opt/app/src -v ${PWD}/tests:/opt/app/tests -v ${PWD}/build:/opt/app/build -u $(id -u ${USER}):$(id -g ${USER})  openfoodfacts-php  php ./vendor/bin/phpunit

.PHONY: phpstan
phpstan:
	docker run  -v ${PWD}/src:/opt/app/src -v ${PWD}/tests:/opt/app/tests -v ${PWD}/build:/opt/app/build  openfoodfacts-php  php ./vendor/bin/phpstan
