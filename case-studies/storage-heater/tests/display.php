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
// Preferred presentation must use eligible frozen values and fail closed on stale plans.
$preferredJournal = ['schema' => 1, 'discarded' => 0, 'records' => [$saved]];
$preferredJournal['records'][0]['evaluation'] = ['eligible' => true, 'state' => 'active', 'observedAt' => $saved['forecast']['issuedAt']];
$preferredContext = ['plan' => $saved['forecast']['plan'], 'configurationHash' => $saved['configurationHash'], 'now' => $saved['forecast']['issuedAt']];
$preferredHtml = FanComparisonView::render($preferredJournal, '', $preferredContext);
$night = reset($saved['forecast']['fanComparison']['nights']);
check(str_contains($preferredHtml, 'Bevorzugt: ' . number_format($night['predictions']['candidate']['kwh'], 2, ',', '.') . ' kWh'), 'Preferred energy is the frozen fan candidate');
check(str_contains($preferredHtml, 'Kontrolle: ' . number_format($night['predictions']['reference']['kwh'], 2, ',', '.') . ' kWh'), 'Paired reference stays visible as control');
foreach (['cancelled', 'replaced', 'unknown'] as $state) {
    $excludedJournal = $preferredJournal;
    $excludedJournal['records'][0]['evaluation'] = ['eligible' => false, 'state' => $state, 'observedAt' => $saved['forecast']['issuedAt']];
    check(!str_contains(FanComparisonView::render($excludedJournal, '', $preferredContext), '<strong>Bevorzugt:'), 'Excluded phase cannot be preferred: ' . $state);
}
foreach (['configuration', 'arrival', 'plan'] as $change) {
    $staleContext = $preferredContext;
    if ($change === 'configuration') {
        $staleContext['configurationHash'] = 'changed';
    } elseif ($change === 'arrival') {
        $staleContext['plan']['inputs']['values']['arrival'] += 3600;
    } else {
        $staleContext['plan']['hash'] = 'changed';
    }
    check(!str_contains(FanComparisonView::render($preferredJournal, '', $staleContext), '<strong>Bevorzugt:'), 'Stale preferred forecast rejected: ' . $change);
}
foreach (['late', 'incomplete'] as $change) {
    $invalidJournal = $preferredJournal;
    if ($change === 'late') {
        $invalidJournal['records'][0]['forecast']['issuedAt'] = $saved['forecast']['phaseStart'];
    } else {
        $invalidJournal['records'][0]['forecast']['start'] = $saved['forecast']['fanComparison']['firstWarmingNight']['start'] + 1;
    }
    check(!str_contains(FanComparisonView::render($invalidJournal, '', $preferredContext), '<strong>Bevorzugt:'), 'Invalid first night cannot be preferred: ' . $change);
}
$activeContext = $preferredContext;
$activeContext['now'] = $saved['forecast']['phaseStart'];
$activeContext['plan']['hash'] = 'timer-consumed';
check(str_contains(FanComparisonView::render($preferredJournal, '', $activeContext), '<strong>Bevorzugt:'), 'Eligible started phase retains frozen energy after timer consumption');
$activeContext['now'] = $saved['forecast']['projection']['arrival'];
check(!str_contains(FanComparisonView::render($preferredJournal, '', $activeContext), '<strong>Bevorzugt:'), 'Past arrival cannot be the current preferred phase');
check($j['records'][0] === $saved && $commands === 0, 'Preferred presentation preserves frozen evidence and never actuates');
echo $checks . " assertions including comparison display passed\n";
