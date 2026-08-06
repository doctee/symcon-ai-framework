<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\DwdNowcast;

final class Profiles
{
    public static function ensure(): void
    {
        \SAEF_EnsureProfile(
            'DWDNOWCAST.DataState',
            1,
            '',
            '',
            '',
            0,
            5,
            1,
            null,
            [
                [0, 'Unconfigured', '', -1],
                [1, 'Fetching', '', -1],
                [2, 'Current', '', -1],
                [3, 'Stale', '', -1],
                [4, 'Warning', '', -1],
                [5, 'Error', '', -1],
            ]
        );
        \SAEF_EnsureProfile('DWDNOWCAST.Minutes', 1, '', '', ' min', -1, 120, 1);
        \SAEF_EnsureProfile('DWDNOWCAST.WaterDepth', 2, '', '', ' mm', 0.0, 500.0, 0.01, 2);
        \SAEF_EnsureProfile('DWDNOWCAST.Intensity', 2, '', '', ' mm/h', 0.0, 1000.0, 0.01, 2);
    }
}
