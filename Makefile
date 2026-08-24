.PHONY: bundle-build bundle-check fileset-build fileset-check control-light-fileset-build control-light-fileset-check media-carousel-fileset-build media-carousel-fileset-check navimow-fileset-build navimow-fileset-check open-meteo-fileset-build open-meteo-fileset-check open-meteo-publication-check open-meteo-publication-prepare media-carousel-publication-check media-carousel-publication-prepare navimow-publication-check navimow-publication-prepare test-bundles test-filesets test-control-light-fileset test-deployment-restart test-deployment-channel test-runtime-source-mirror test-runtime-health-probe test-helpers test-mqtt-exporter-core test-mqtt-exporter-runtime test-mqtt-exporter-reconcile test-mqtt-exporter-execute test-mqtt-exporter-dispatch test-mqtt-exporter-cleanup test-mqtt-exporter-fixtures test-control-light-core test-control-light-runtime test-control-light-topology test-control-light-runtime-mirror test-navimow-rest-auth test-navimow-pilot test-navimow-mqtt test-navimow-distribution test-media-carousel test-module-publication test-open-meteo-publication test-open-meteo-offline lint phpstan phpstan-bundle phpcs check

bundle-build:
	composer bundle:build

bundle-check:
	composer bundle:check

fileset-build:
	composer fileset:build

fileset-check:
	composer fileset:check

control-light-fileset-build:
	composer control-light:fileset-build

control-light-fileset-check:
	composer control-light:fileset-check

media-carousel-fileset-build:
	composer media-carousel:fileset-build

media-carousel-fileset-check:
	composer media-carousel:fileset-check

navimow-fileset-build:
	composer navimow:fileset-build

navimow-fileset-check:
	composer navimow:fileset-check

open-meteo-fileset-build:
	php tools/build-symcon-module-fileset.php deployments/symcon/open-meteo-module.fileset.json

open-meteo-fileset-check:
	php tools/build-symcon-module-fileset.php --check deployments/symcon/open-meteo-module.fileset.json

open-meteo-publication-check:
	php tools/publish-open-meteo-module.php --check

open-meteo-publication-prepare:
	php tools/publish-open-meteo-module.php --prepare

media-carousel-publication-check:
	php tools/publish-symcon-module.php --contract=deployments/symcon/media-carousel-publication.json --check

media-carousel-publication-prepare:
	php tools/publish-symcon-module.php --contract=deployments/symcon/media-carousel-publication.json --prepare

navimow-publication-check:
	php tools/publish-symcon-module.php --contract=deployments/symcon/navimow-publication.json --check

navimow-publication-prepare:
	php tools/publish-symcon-module.php --contract=deployments/symcon/navimow-publication.json --prepare

test-bundles:
	composer test:bundles

test-filesets:
	composer test:filesets

test-control-light-fileset:
	composer test:control-light-fileset

test-deployment-restart:
	composer test:deployment-restart

test-deployment-channel:
	composer test:deployment-channel

test-runtime-source-mirror:
	composer test:runtime-source-mirror

test-runtime-health-probe:
	composer test:runtime-health-probe

test-helpers:
	composer test:helpers

test-mqtt-exporter-core:
	composer test:mqtt-exporter-core

test-mqtt-exporter-runtime:
	composer test:mqtt-exporter-runtime

test-mqtt-exporter-reconcile:
	composer test:mqtt-exporter-reconcile

test-mqtt-exporter-execute:
	composer test:mqtt-exporter-execute

test-mqtt-exporter-dispatch:
	composer test:mqtt-exporter-dispatch

test-mqtt-exporter-cleanup:
	composer test:mqtt-exporter-cleanup

test-mqtt-exporter-fixtures:
	composer test:mqtt-exporter-fixtures

test-control-light-core:
	composer test:control-light-core

test-control-light-runtime:
	composer test:control-light-runtime

test-control-light-topology:
	composer test:control-light-topology

test-control-light-runtime-mirror:
	composer test:control-light-runtime-mirror

test-navimow-rest-auth:
	composer test:navimow-rest-auth

test-navimow-pilot:
	composer test:navimow-pilot

test-navimow-mqtt:
	composer test:navimow-mqtt

test-navimow-distribution:
	composer test:navimow-distribution

test-media-carousel:
	composer test:media-carousel

test-module-publication:
	composer test:module-publication

test-open-meteo-offline:
	case-studies/open-meteo/tools/check-offline.sh

test-open-meteo-publication:
	php case-studies/open-meteo/tests/publication.php

lint:
	composer lint

phpstan:
	composer phpstan

phpstan-bundle:
	composer phpstan:bundle

phpcs:
	composer phpcs

check: test-open-meteo-offline
	composer check
