<?php
declare(strict_types=1);

/**
 * SAEF Helper: EnsureProfile
 *
 * Idempotently creates or validates a variable profile.
 */

require_once __DIR__ . '/../common/Validation.php';

if (!defined('SAEF_HELPER_ENSURE_PROFILE')) {
    define('SAEF_HELPER_ENSURE_PROFILE', true);

    /**
     * Ensures that a variable profile exists and has the expected type.
     *
     * @param string     $name         Profile name.
     * @param int        $type         Profile type: 0 bool, 1 int, 2 float, 3 string.
     * @param string     $icon         Optional profile icon.
     * @param string     $prefix       Optional prefix text.
     * @param string     $suffix       Optional suffix text.
     * @param int|float|null $minValue Optional minimum value.
     * @param int|float|null $maxValue Optional maximum value.
     * @param int|float|null $stepSize Optional step size.
     * @param int|null    $digits      Optional digits for float profiles.
     * @param array       $associations Optional associations: [value, name, icon, color].
     */
    function SAEF_EnsureProfile(
        string $name,
        int $type,
        string $icon = '',
        string $prefix = '',
        string $suffix = '',
        int|float|null $minValue = null,
        int|float|null $maxValue = null,
        int|float|null $stepSize = null,
        ?int $digits = null,
        array $associations = []
    ): void {
        SAEF_ValidateVariableType($type);

        if ($name === '') {
            throw new InvalidArgumentException('Profile name must not be empty.');
        }

        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, $type);
        } else {
            $profile = IPS_GetVariableProfile($name);

            if ($profile['ProfileType'] !== $type) {
                throw new RuntimeException(sprintf(
                    'Profile "%s" has type %d, expected %d.',
                    $name,
                    $profile['ProfileType'],
                    $type
                ));
            }
        }

        IPS_SetVariableProfileIcon($name, $icon);
        IPS_SetVariableProfileText($name, $prefix, $suffix);

        if ($digits !== null) {
            if ($type !== 2) {
                throw new InvalidArgumentException('digits may only be set for float profiles.');
            }

            IPS_SetVariableProfileDigits($name, $digits);
        }

        if ($minValue !== null || $maxValue !== null || $stepSize !== null) {
            if ($minValue === null || $maxValue === null || $stepSize === null) {
                throw new InvalidArgumentException('minValue, maxValue and stepSize must be provided together.');
            }

            IPS_SetVariableProfileValues($name, $minValue, $maxValue, $stepSize);
        }

        foreach ($associations as $association) {
            if (!is_array($association) || count($association) !== 4) {
                throw new InvalidArgumentException('Profile associations must be arrays: [value, name, icon, color].');
            }

            IPS_SetVariableProfileAssociation(
                $name,
                $association[0],
                (string)$association[1],
                (string)$association[2],
                (int)$association[3]
            );
        }
    }
}
