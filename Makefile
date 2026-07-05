.PHONY: lint phpstan phpcs check

lint:
	composer lint

phpstan:
	composer phpstan

phpcs:
	composer phpcs

check:
	composer check
