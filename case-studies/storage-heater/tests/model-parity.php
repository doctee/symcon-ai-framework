<?php

declare(strict_types=1);

require_once __DIR__ . '/../distribution/libs/Forecast.php';
use SAEF\StorageHeater\Forecast;

// Optional private regression fixture produced by tools/train.py. Never committed.
$rows = Forecast::decode(file_get_contents($argv[1]));
$maximumDifference = 0.0;
foreach ($rows as $row) {
    $features = array_combine(Forecast::FEATURES, $row['features']);
    $issue = (new DateTimeImmutable($row['date'] . ' 21:45', new DateTimeZone('Europe/Berlin')))->getTimestamp();
    $result = Forecast::predict($row['model'], $features, $issue);
    $difference = abs($result['kwh'] - $row['prediction']);
    if ($difference > 1e-9) {
        throw new RuntimeException('PHP/Python model drift: ' . $row['date']);
    }
    $maximumDifference = max($maximumDifference, $difference);
}
echo count($rows) . ' historical predictions match Python; maximum difference ' . $maximumDifference . " kWh\n";
