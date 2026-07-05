<?php
declare(strict_types=1);

/**
 * SAEF Template: Configuration Script
 *
 * Purpose:
 * - create/update an owned Symcon object structure,
 * - validate configuration before changing state,
 * - use SAEF helpers instead of reimplementing object creation logic.
 */

require_once __DIR__ . '/../helpers/object/EnsureCategory.php';
require_once __DIR__ . '/../helpers/object/EnsureVariable.php';
require_once __DIR__ . '/../helpers/object/EnsureEvent.php';

$config = [
    'parentID' => 0,

    'category' => [
        'ident' => 'SAEF_TEMPLATE',
        'name' => 'SAEF Template',
    ],

    'variables' => [
        [
            'ident' => 'STATE',
            'name' => 'State',
            'type' => 1,
            'profile' => '',
        ],
        [
            'ident' => 'LAST_RUN',
            'name' => 'Last Run',
            'type' => 1,
            'profile' => '~UnixTimestamp',
        ],
    ],

    'event' => [
        'ident' => 'PERIODIC_UPDATE',
        'name' => 'Periodic Update',
        'targetScriptID' => $_IPS['SELF'],
        'intervalSeconds' => 300,
        'active' => false,
    ],
];

try {
    if (!IPS_ObjectExists($config['parentID'])) {
        throw new InvalidArgumentException('Configured parentID does not exist.');
    }

    $categoryID = SAEF_EnsureCategory(
        $config['parentID'],
        $config['category']['ident'],
        $config['category']['name']
    );

    foreach ($config['variables'] as $variable) {
        SAEF_EnsureVariable(
            $categoryID,
            $variable['ident'],
            $variable['name'],
            $variable['type'],
            $variable['profile']
        );
    }

    SAEF_EnsureCyclicScriptEvent(
        $categoryID,
        $config['event']['ident'],
        $config['event']['name'],
        $config['event']['targetScriptID'],
        $config['event']['intervalSeconds'],
        $config['event']['active']
    );

    SetValue(IPS_GetObjectIDByIdent('LAST_RUN', $categoryID), time());

    IPS_LogMessage('SAEF Configuration Template', 'Configuration completed successfully');
} catch (Throwable $exception) {
    IPS_LogMessage('SAEF Configuration Template', 'Configuration failed: ' . $exception->getMessage());
    throw $exception;
}
