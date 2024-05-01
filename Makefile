
php-setup:
	docker build -t openfoodfacts-php -f ./docker/Dockerfile .

fix:
	docker run  -v ${PWD}/src:/opt/app/tests -v ${PWD}/src:/opt/app/tests  openfoodfacts-php  php ./vendor/bin/php-cs-fixer fix

test:
	docker run  -v ${PWD}/src:/opt/app/src -v ${PWD}/tests:/opt/app/tests -v ${PWD}/build:/opt/app/build -u $(id -u ${USER}):$(id -g ${USER})  openfoodfacts-php  php ./vendor/bin/phpunit
