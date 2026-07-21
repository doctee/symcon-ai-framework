.PHONY: bundle-build bundle-check fileset-build fileset-check control-light-fileset-build control-light-fileset-check test-bundles test-filesets test-control-light-fileset test-deployment-restart test-deployment-channel test-helpers test-mqtt-exporter-core test-mqtt-exporter-runtime test-mqtt-exporter-reconcile test-mqtt-exporter-execute test-mqtt-exporter-dispatch test-mqtt-exporter-cleanup test-mqtt-exporter-fixtures test-control-light-core test-control-light-runtime test-control-light-topology test-control-light-runtime-mirror test-navimow-rest-auth test-navimow-pilot test-navimow-distribution lint phpstan phpstan-bundle phpcs check

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

test-navimow-distribution:
	composer test:navimow-distribution

lint:
	composer lint

phpstan:
	composer phpstan

phpstan-bundle:
	composer phpstan:bundle

phpcs:
	composer phpcs

check:
	composer check
