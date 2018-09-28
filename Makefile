tests: unit behat

release:
	./vendor/bin/RMT release

unit:
	php ./vendor/bin/phpunit

behat:
	php ./vendor/bin/behat --colors -vvv

database:
	rm ./examples/rulerz.db || true
	./vendor/bin/doctrine orm:schema-tool:create
	./examples/scripts/load_fixtures.php

rusty:
	php ./vendor/bin/rusty check --no-execute README.md
