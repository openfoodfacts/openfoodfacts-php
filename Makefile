
php-setup:
	test -e .env || cp .env.example .env
	docker compose down
	docker compose rm
	docker compose build
	docker compose up -d --force-recreate
	make composer install

install:
	docker compose exec php-fpm bash -c 'XDEBUG_MODE=off composer install'

fix:
	docker compose exec php-fpm bash -c 'XDEBUG_MODE=off ./vendor/bin/php-cs-fixer fix'

test:
	docker compose exec php-fpm bash -c 'XDEBUG_MODE=off ./vendor/bin/phpunit'

composer:
	docker compose exec php-fpm bash -c "XDEBUG_MODE=off composer $(filter-out $@,$(MAKECMDGOALS))"