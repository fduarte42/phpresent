.PHONY: install dev build test test-unit test-integration stan cs cs-fix migrate serve up down

install:
	composer install
	npm install

dev:
	npm run dev

build:
	npm run build

test:
	composer test

test-unit:
	composer test:unit

test-integration:
	composer test:integration

stan:
	composer stan

cs:
	composer cs-check

cs-fix:
	composer cs-fix

migrate:
	composer migrate

serve:
	composer serve

up:
	docker compose up --build

down:
	docker compose down
