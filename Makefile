build: lint

deps:
	wget -q https://getcomposer.org/composer.phar -O ./composer.phar
	chmod +x composer.phar
	wget -q https://phar.phpunit.de/phpunit.phar -O ./phpunit.phar
	chmod +x phpunit.phar
	php composer.phar install

lint:
	php -l index.php
	# ./vendor/bin/phpcbf --standard=PSR2 src
	./vendor/bin/phpcs --standard=PSR2 --warning-severity=0 src
	./vendor/bin/phpcs --standard=Squiz --sniffs=Squiz.Commenting.FunctionComment,Squiz.Commenting.FunctionCommentThrowTag,Squiz.Commenting.ClassComment,Squiz.Commenting.VariableComment src

test:
	php phpunit.phar --bootstrap vendor/autoload.php tests/

serve:
	php -S localhost:8080

docker:
	docker build -t vse .

docker-run:
	docker run -v `pwd`:/vse -ti --rm -t vse

.PHONY: test
