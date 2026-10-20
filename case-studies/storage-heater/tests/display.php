<?php

declare(strict_types=1);

require __DIR__ . '/early.php';

use SAEF\StorageHeater\FanComparison;
use SAEF\StorageHeater\FanComparisonView;

$j = ['schema' => 1, 'discarded' => 0, 'records' => [$saved]];
$empty = ['schema' => 1, 'discarded' => 0, 'records' => []];
$context = ['plan' => $saved['forecast']['plan'], 'configurationHash' => $saved['configurationHash'], 'now' => $saved['forecast']['issuedAt']];
$window = FanComparison::issueWindow($saved['forecast']['phaseStart']);
$context['now'] = $window['earliest'] - 60;
$html = FanComparisonView::render($empty, 'Wartet', $context);
check(str_contains($html, 'Speicherung möglich ab') && str_contains($html, '21.10.2026 07:00'), 'Waiting display gives exact eligibility date');
check(str_contains($html, '22.10.2026 22:00') && str_contains($html, '23.10.2026 07:00'), 'First-night measurement window is explicit');
check(str_contains($html, '22.10.2026 16:15') && str_contains($html, 'Noch nicht gespeichert'), 'Deadline and unsaved state are explicit');
file_put_contents($argv[1] . '/comparison-waiting-preview.html', $html);
$context['now'] = $window['earliest'];
$html = FanComparisonView::render($empty, 'Wetterabruf fehlgeschlagen', $context);
check(str_contains($html, 'nächster erfolgreicher Prüflauf') && str_contains($html, 'Wetterabruf fehlgeschlagen'), 'Eligible data failure shows unsaved state and cause together');
$html = FanComparisonView::render($j, 'Gespeichert', $context);
check(str_contains($html, 'Vorab gespeichert am 21.10.2026 07:00') && !str_contains($html, 'Noch nicht gespeichert'), 'Matching frozen record gives actual successful save time');
file_put_contents($argv[1] . '/comparison-saved-preview.html', $html);
$changed = $context;
$changed['plan']['hash'] = 'changed-plan';
$html = FanComparisonView::render($j, 'Plan geändert', $changed);
check(str_contains($html, 'Noch nicht gespeichert') && str_contains($html, '(dieser Planstand)'), 'Old snapshot is not shown as saved for a changed plan');
$changed = $context;
$changed['configurationHash'] = 'changed-model';
check(str_contains(FanComparisonView::render($j, 'Modell geändert', $changed), 'Noch nicht gespeichert'), 'Old model record cannot confirm current selection');
$context['now'] = $window['deadline'];
$html = FanComparisonView::render($empty, 'Frist verpasst', $context);
check(str_contains($html, 'Vorabfrist verpasst') || str_contains($html, 'Aufwärmstart unbekannt'), 'Expired start never says pending automatic save');
$context['now'] = $window['earliest'];
$context['plan']['inputs']['values']['storageRequested'] = true;
$html = FanComparisonView::render($j, 'Aufwärmphase läuft', $context);
check(str_contains($html, 'Vorab gespeichert am') && str_contains($html, '(dieser Planstand)'), 'Saved phase remains visible after timer has fired');
check(str_contains($html, 'Aufwärmphase gestartet.') && str_contains($html, 'Gespeicherte Vorabprognosen werden weiter'), 'Started phase confirms retained comparison, not an unknown start');
check(!str_contains($html, 'Aufwärmstart unbekannt'), 'Known started phase never claims unknown start');
check(str_contains(FanComparisonView::render($empty, '', $context), 'Keine Vorabprognose gespeichert.'), 'Started phase without evidence does not claim saved forecasts');
$unknown = $context;
$unknown['plan']['inputs']['values']['storageRequested'] = false;
$unknown['plan']['inputs']['events']['storageEvent']['EventActive'] = false;
check(str_contains(FanComparisonView::render($j, '', $unknown), 'Aufwärmstart unbekannt'), 'Absent future timer is distinguished from a started phase');
$context['plan']['inputs']['values']['arrival'] = 0;
$html = FanComparisonView::render($empty, 'Bereit', $context);
check(str_contains($html, 'Keine aktive zukünftige Aufwärmvorwahl') && !str_contains($html, 'Vorab gespeichert am'), 'No selection never implies successful storage');
file_put_contents($argv[1] . '/comparison-idle-preview.html', $html);
check($j['records'][0] === $saved && $commands === 0, 'Presentation preserves frozen evidence and never actuates');
echo $checks . " assertions including comparison display passed\n";
