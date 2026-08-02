<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use SAEF\CaseStudy\OpenMeteo\ForecastStateReducer;

$hash = hash('sha256', 'configuration-a');
$state = ForecastStateReducer::initial($hash, 2);
$state = ForecastStateReducer::success($state, 1000, 900, 10000);
same(ForecastStateReducer::STATE_CURRENT, $state['state'], 'First success state differs.');
same(true, $state['hasData'], 'First success must retain data.');

$state = ForecastStateReducer::failure($state, 1100, 'transport_timeout', true);
same(ForecastStateReducer::STATE_WARNING, $state['state'], 'Last-good failure must warn.');
same(true, $state['hasData'], 'Temporary failure must preserve last-good data.');
same(1, $state['retryCount'], 'Retry progression differs.');
$state = ForecastStateReducer::failure($state, 1200, 'partial_orientation', true);
$state = ForecastStateReducer::failure($state, 1300, 'partial_orientation', true);
same(2, $state['retryCount'], 'Retry count must remain bounded.');

$state = ForecastStateReducer::evaluateFreshness($state, 2000, 500);
same(ForecastStateReducer::STATE_STALE, $state['state'], 'Stale transition differs.');

$changed = ForecastStateReducer::configurationChanged(
    $state,
    hash('sha256', 'configuration-b')
);
same(false, $changed['hasData'], 'Changed configuration must invalidate cached output.');
same(null, $changed['lastSuccess'], 'Changed configuration retained a success timestamp.');

$invalid = ForecastStateReducer::initial(hash('sha256', 'invalid'), 3);
$invalid = ForecastStateReducer::failure($invalid, 100, 'configuration_invalid', false);
same(ForecastStateReducer::STATE_ERROR, $invalid['state'], 'Configuration failure must error.');
same(3, $invalid['retryCount'], 'Non-retryable failure must exhaust retries.');

echo "state-reducer: ok\n";
