<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/OpenMeteo/LocationDefinition.php';

use SAEF\CaseStudy\OpenMeteo\LocationDefinition;

class SharedLocation extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_CONFIGURATION_ERROR = 200;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyBoolean('LocationConfigured', false);
        $this->RegisterPropertyString('LocationKey', '');
        $this->RegisterPropertyFloat('Latitude', 0.0);
        $this->RegisterPropertyFloat('Longitude', 0.0);
        $this->RegisterPropertyBoolean('UseElevation', false);
        $this->RegisterPropertyFloat('Elevation', 0.0);
        $this->RegisterPropertyString('Timezone', 'Europe/Berlin');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        if (!$this->ReadPropertyBoolean('LocationConfigured')) {
            $this->SetStatus(self::STATUS_INACTIVE);

            return;
        }

        try {
            LocationDefinition::normalize($this->configuration());
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable) {
            $this->SetStatus(self::STATUS_CONFIGURATION_ERROR);
        }
    }

    public function GetDescriptor(): string
    {
        if (!$this->ReadPropertyBoolean('LocationConfigured')) {
            return $this->result(false, 'configuration_missing');
        }

        try {
            return $this->encode([
                'success' => true,
                'location' => LocationDefinition::normalize($this->configuration()),
            ]);
        } catch (Throwable) {
            return $this->result(false, 'configuration_invalid');
        }
    }

    /** @return array<string, float|string|null> */
    private function configuration(): array
    {
        return [
            'key' => trim($this->ReadPropertyString('LocationKey')),
            'latitude' => $this->ReadPropertyFloat('Latitude'),
            'longitude' => $this->ReadPropertyFloat('Longitude'),
            'timezone' => trim($this->ReadPropertyString('Timezone')),
            'elevation' => $this->ReadPropertyBoolean('UseElevation')
                ? $this->ReadPropertyFloat('Elevation')
                : null,
        ];
    }

    private function result(bool $success, string $code): string
    {
        return $this->encode(['success' => $success, 'code' => $code]);
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
