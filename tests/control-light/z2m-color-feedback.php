<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ControlLightCore;

require_once __DIR__ . '/../../case-studies/control-light/candidate/ControlLightCore.php';

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$base = ['preset' => 'Z2M', 'identColor' => 'color', 'brightnessSemantics' => 'reported'];
$ordinary = ControlLightCore::normalizeConfiguration($base);
$configured = ControlLightCore::normalizeConfiguration($base + ['colorFeedbackQuantization' => 'z2m-v6-rgb']);
$vectors = json_decode(file_get_contents(__DIR__ . '/fixtures/z2m-color-feedback.json'), true, 16, JSON_THROW_ON_ERROR)['vectors'];
foreach ($vectors as $vector) {
    $input = (int)hexdec($vector['input']);
    $valid = array_unique([$input, (int)hexdec($vector['xy']), (int)hexdec($vector['hs'])]);
    $check(ControlLightCore::localToTarget('color', $input, $configured) === $input, 'Original color command changed');
    foreach ($valid as $report) {
        $check(ControlLightCore::targetValueMatches('color', $input, $report, $configured), 'Exact representation rejected');
        $check(ControlLightCore::targetValueMatches('color', $input, $report, $ordinary) === ($input === $report), 'Default RGB comparison changed');
        foreach ([$report ^ 1, $report ^ 0x100, $report ^ 0x10000] as $wrong) {
            if (!in_array($wrong, $valid, true)) {
                $check(!ControlLightCore::targetValueMatches('color', $input, $wrong, $configured), 'Adjacent unconfirmed RGB accepted');
            }
        }
    }
    foreach (['16764791', null, true, -1, 0x1000000, 1.5] as $bad) {
        $check(!ControlLightCore::targetValueMatches('color', $input, $bad, $configured), 'Invalid RGB feedback accepted');
    }
}
$check(!ControlLightCore::targetValueMatches('color', 0, 0, $configured), 'Ignored native black became confirmed');
$check(ControlLightCore::targetValueMatches('color', 0, 0, $ordinary), 'Ordinary RGB black changed');
foreach ([0, -1, 0x1000000, '123456', false] as $bad) {
    try {
        ControlLightCore::localToTarget('color', $bad, $configured);
        $check(false, 'Invalid or non-actionable color reached dispatch');
    } catch (InvalidArgumentException) {
        $check(true, 'Invalid/non-actionable request rejected');
    }
}
foreach ([['preset' => 'MATTER'], ['identDim' => ''], ['identColor' => ''], ['colorTargetFormat' => 'HS_ARRAY_STRING']] as $wrong) {
    try {
        ControlLightCore::normalizeConfiguration(array_replace($base, $wrong) + ['colorFeedbackQuantization' => 'z2m-v6-rgb']);
        $check(false, 'Wrong transport accepted the profile');
    } catch (InvalidArgumentException) {
        $check(true, 'Wrong transport rejected');
    }
}
echo 'Z2M color feedback: ' . $checks . " checks passed (offline).\n";
