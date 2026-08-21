<?php

declare(strict_types=1);

class MediaCarousel extends IPSModuleStrict
{
    private const STATUS_INVALID_CONFIGURATION = 200;
    private const MAX_ITEM_COUNT = 50;

    /** @var list<int> */
    private const MEDIA_MESSAGES = [
        MM_DELETE,
        MM_CHANGEFILE,
        MM_AVAILABLE,
        MM_UPDATE,
        OM_CHANGENAME,
    ];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('MediaItems', '[]');
        $this->RegisterPropertyBoolean('AutoLoop', true);
        $this->RegisterPropertyInteger('LoopSeconds', 8);
        $this->RegisterPropertyInteger('LoadTimeoutSeconds', 10);
        $this->RegisterPropertyInteger('RetryCount', 2);
        $this->RegisterPropertyInteger('PauseAfterInteractionSeconds', 15);
        $this->RegisterPropertyInteger('TransitionMilliseconds', 320);
        $this->RegisterPropertyString('FitMode', 'cover');
        $this->RegisterPropertyBoolean('ShowTitles', true);
        $this->RegisterPropertyBoolean('ShowDots', true);
        $this->RegisterPropertyBoolean('ShowArrows', true);
        $this->RegisterPropertyInteger('MaxMediaMegabytes', 5);

        $this->RegisterAttributeString('RegisteredMediaIDs', '[]');

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->unregisterConfiguredMedia();

        try {
            $items = $this->resolveMediaItems();
            $this->validatePresentationConfiguration();
            $this->registerConfiguredMedia($items);

            $this->SetStatus($items === [] ? IS_INACTIVE : IS_ACTIVE);
            $this->UpdateVisualizationValue(
                $this->encodeMessage($this->createBootstrapMessage(false))
            );
        } catch (Throwable $exception) {
            $this->WriteAttributeString('RegisteredMediaIDs', '[]');
            $this->SetStatus(self::STATUS_INVALID_CONFIGURATION);
            $this->SendDebug('Invalid configuration', $exception->getMessage(), 0);
            $this->UpdateVisualizationValue(
                $this->encodeMessage([
                    'action' => 'configurationError',
                    'message' => $this->Translate('No valid images configured'),
                ])
            );
        }
    }

    /**
     * @param array<int, mixed> $data
     */
    public function MessageSink(
        int $timeStamp,
        int $senderID,
        int $message,
        array $data
    ): void {
        $registeredIDs = $this->readRegisteredMediaIDs();
        $index = array_search($senderID, $registeredIDs, true);
        if ($index === false) {
            return;
        }

        if ($message === OM_CHANGENAME || $message === MM_DELETE) {
            $this->ApplyChanges();

            return;
        }

        if (!in_array($message, self::MEDIA_MESSAGES, true)) {
            return;
        }

        try {
            $configurationRevision = $this->configurationRevision($this->resolveMediaItems());
            $this->UpdateVisualizationValue(
                $this->encodeMessage([
                    'action'                => 'invalidate',
                    'configurationRevision' => $configurationRevision,
                    'index'                 => $index,
                ])
            );
        } catch (Throwable $exception) {
            $this->SendDebug('Media invalidation failed', $exception->getMessage(), 0);
            $this->ApplyChanges();
        }
    }

    public function RequestAction(string $ident, mixed $value): void
    {
        if ($ident !== 'LoadMedia') {
            throw new InvalidArgumentException('Unsupported action: ' . $ident);
        }

        $requestID = '';

        try {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Media request must be JSON text.');
            }

            $request = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($request)) {
                throw new InvalidArgumentException('Media request must be a JSON object.');
            }

            $requestID = $this->readRequestID($request);
            $index = $this->readRequestIndex($request);
            $items = $this->resolveMediaItems();

            if (!array_key_exists($index, $items)) {
                throw new OutOfRangeException('Requested media index is outside the configured sequence.');
            }

            $this->UpdateVisualizationValue(
                $this->encodeMessage(
                    $this->createMediaMessage(
                        $items,
                        $index,
                        $requestID,
                        $this->configurationRevision($items)
                    )
                )
            );
        } catch (Throwable $exception) {
            $this->SendDebug('LoadMedia failed', $exception->getMessage(), 0);
            $this->UpdateVisualizationValue(
                $this->encodeMessage([
                    'action'    => 'mediaError',
                    'requestID' => $requestID,
                    'message'   => $this->Translate('Image unavailable'),
                ])
            );
        }
    }

    public function GetVisualizationTile(): string
    {
        $moduleHTML = file_get_contents(__DIR__ . '/module.html');
        $moduleJavaScript = file_get_contents(__DIR__ . '/carousel.js');
        if ($moduleHTML === false || $moduleJavaScript === false) {
            throw new RuntimeException('MediaCarousel frontend files are unavailable.');
        }

        $moduleHTML = str_replace(
            '/* SAEF_MEDIA_CAROUSEL_SCRIPT */',
            $moduleJavaScript,
            $moduleHTML
        );

        try {
            $message = $this->createBootstrapMessage(true);
        } catch (Throwable $exception) {
            $this->SendDebug('Tile bootstrap failed', $exception->getMessage(), 0);
            $message = [
                'action' => 'configurationError',
                'message' => $this->Translate('No valid images configured'),
            ];
        }

        $messageJSON = $this->encodeMessage($message);
        $initialHandling = '<script>handleMessage(' . json_encode(
            $messageJSON,
            JSON_THROW_ON_ERROR
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        ) . ');</script>';

        return $moduleHTML . $initialHandling;
    }

    /**
     * @return list<array{mediaID: int, title: string, mimeType: string}>
     */
    private function resolveMediaItems(): array
    {
        $rows = json_decode(
            $this->ReadPropertyString('MediaItems'),
            true,
            32,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('MediaItems must be a JSON list.');
        }
        if (count($rows) > self::MAX_ITEM_COUNT) {
            throw new InvalidArgumentException('MediaItems exceeds the supported item count.');
        }

        $items = [];
        $seenMediaIDs = [];

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('MediaItems row must be an object.');
            }

            $enabled = $row['Enabled'] ?? true;
            if (!is_bool($enabled)) {
                throw new InvalidArgumentException('Enabled must be boolean.');
            }
            if (!$enabled) {
                continue;
            }

            $mediaID = $row['MediaID'] ?? null;
            if (!is_int($mediaID) || $mediaID <= 0) {
                throw new InvalidArgumentException(
                    sprintf('MediaItems row %d has no positive MediaID.', $rowIndex)
                );
            }
            if (isset($seenMediaIDs[$mediaID])) {
                throw new InvalidArgumentException('Duplicate MediaID is not allowed.');
            }
            if (!IPS_MediaExists($mediaID)) {
                throw new InvalidArgumentException('Configured media object does not exist.');
            }

            $media = IPS_GetMedia($mediaID);
            if ($media['MediaType'] !== MEDIATYPE_IMAGE) {
                throw new InvalidArgumentException('Configured object is not image media.');
            }

            $mediaFile = $media['MediaFile'];
            if ($mediaFile === '') {
                throw new InvalidArgumentException('Configured media object has no media file.');
            }

            $title = $row['Title'] ?? '';
            if (!is_string($title)) {
                throw new InvalidArgumentException('Media title must be text.');
            }
            $title = trim($title);
            if (strlen($title) > 120) {
                throw new InvalidArgumentException('Media title exceeds 120 bytes.');
            }
            if ($title === '') {
                $title = IPS_GetName($mediaID);
            }

            $items[] = [
                'mediaID'  => $mediaID,
                'title'    => $title,
                'mimeType' => $this->mimeTypeFromMediaFile($mediaFile),
            ];
            $seenMediaIDs[$mediaID] = true;
        }

        return $items;
    }

    private function validatePresentationConfiguration(): void
    {
        $this->assertIntegerRange('LoopSeconds', 3, 120);
        $this->assertIntegerRange('LoadTimeoutSeconds', 2, 30);
        $this->assertIntegerRange('RetryCount', 0, 5);
        $this->assertIntegerRange('PauseAfterInteractionSeconds', 0, 120);
        $this->assertIntegerRange('TransitionMilliseconds', 100, 1000);
        $this->assertIntegerRange('MaxMediaMegabytes', 1, 20);

        if (!in_array($this->ReadPropertyString('FitMode'), ['cover', 'contain'], true)) {
            throw new InvalidArgumentException('FitMode must be cover or contain.');
        }
    }

    private function assertIntegerRange(string $property, int $minimum, int $maximum): void
    {
        $value = $this->ReadPropertyInteger($property);
        if ($value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException($property . ' is outside the supported range.');
        }
    }

    /**
     * @param list<array{mediaID: int, title: string, mimeType: string}> $items
     */
    private function registerConfiguredMedia(array $items): void
    {
        $registerMessage = [$this, 'Register' . 'Message'];
        if (!is_callable($registerMessage)) {
            throw new LogicException('RegisterMessage is unavailable.');
        }

        $mediaIDs = [];
        foreach ($items as $item) {
            $mediaID = $item['mediaID'];
            $this->RegisterReference($mediaID);
            foreach (self::MEDIA_MESSAGES as $message) {
                $registerMessage($mediaID, $message);
            }
            $mediaIDs[] = $mediaID;
        }

        $this->WriteAttributeString(
            'RegisteredMediaIDs',
            json_encode($mediaIDs, JSON_THROW_ON_ERROR)
        );
    }

    private function unregisterConfiguredMedia(): void
    {
        $unregisterMessage = [$this, 'Unregister' . 'Message'];
        if (!is_callable($unregisterMessage)) {
            throw new LogicException('UnregisterMessage is unavailable.');
        }

        foreach ($this->readRegisteredMediaIDs() as $mediaID) {
            foreach (self::MEDIA_MESSAGES as $message) {
                $unregisterMessage($mediaID, $message);
            }
            $this->UnregisterReference($mediaID);
        }

        $this->WriteAttributeString('RegisteredMediaIDs', '[]');
    }

    /** @return list<int> */
    private function readRegisteredMediaIDs(): array
    {
        try {
            $value = json_decode(
                $this->ReadAttributeString('RegisteredMediaIDs'),
                true,
                16,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $mediaIDs = [];
        foreach ($value as $mediaID) {
            if (is_int($mediaID) && $mediaID > 0) {
                $mediaIDs[] = $mediaID;
            }
        }

        return $mediaIDs;
    }

    /**
     * @return array<string, mixed>
     */
    private function createBootstrapMessage(bool $includeInitialMedia): array
    {
        $items = $this->resolveMediaItems();
        $this->validatePresentationConfiguration();
        $configurationRevision = $this->configurationRevision($items);

        $message = [
            'action'                => 'bootstrap',
            'instanceID'            => $this->InstanceID,
            'configurationRevision' => $configurationRevision,
            'items'                 => array_map(
                static fn (array $item): array => ['title' => $item['title']],
                $items
            ),
            'settings'              => [
                'autoLoop'                     => $this->ReadPropertyBoolean('AutoLoop'),
                'loopSeconds'                  => $this->ReadPropertyInteger('LoopSeconds'),
                'loadTimeoutSeconds'           => $this->ReadPropertyInteger('LoadTimeoutSeconds'),
                'retryCount'                   => $this->ReadPropertyInteger('RetryCount'),
                'pauseAfterInteractionSeconds' => $this->ReadPropertyInteger(
                    'PauseAfterInteractionSeconds'
                ),
                'transitionMilliseconds'       => $this->ReadPropertyInteger(
                    'TransitionMilliseconds'
                ),
                'fitMode'                      => $this->ReadPropertyString('FitMode'),
                'showTitles'                   => $this->ReadPropertyBoolean('ShowTitles'),
                'showDots'                     => $this->ReadPropertyBoolean('ShowDots'),
                'showArrows'                   => $this->ReadPropertyBoolean('ShowArrows'),
            ],
        ];

        if ($includeInitialMedia && $items !== []) {
            $message['initialMedia'] = $this->createMediaMessage(
                $items,
                0,
                'initial',
                $configurationRevision
            );
        }

        return $message;
    }

    /**
     * @param list<array{mediaID: int, title: string, mimeType: string}> $items
     * @return array<string, int|string>
     */
    private function createMediaMessage(
        array $items,
        int $index,
        string $requestID,
        string $configurationRevision
    ): array {
        $item = $items[$index];
        $content = IPS_GetMediaContent($item['mediaID']);
        if ($content === '') {
            throw new RuntimeException('Media content is empty.');
        }

        $estimatedBytes = intdiv(strlen($content) * 3, 4);
        $maximumBytes = $this->ReadPropertyInteger('MaxMediaMegabytes') * 1024 * 1024;
        if ($estimatedBytes > $maximumBytes) {
            throw new LengthException('Media content exceeds MaxMediaMegabytes.');
        }

        return [
            'action'                => 'media',
            'configurationRevision' => $configurationRevision,
            'requestID'             => $requestID,
            'index'                 => $index,
            'contentRevision'       => hash('sha256', $content),
            'source'                => 'data:' . $item['mimeType'] . ';base64,' . $content,
        ];
    }

    /**
     * @param list<array{mediaID: int, title: string, mimeType: string}> $items
     */
    private function configurationRevision(array $items): string
    {
        return hash(
            'sha256',
            json_encode(
                [
                    'items'                         => $items,
                    'autoLoop'                      => $this->ReadPropertyBoolean('AutoLoop'),
                    'loopSeconds'                   => $this->ReadPropertyInteger('LoopSeconds'),
                    'loadTimeoutSeconds'            => $this->ReadPropertyInteger(
                        'LoadTimeoutSeconds'
                    ),
                    'retryCount'                    => $this->ReadPropertyInteger('RetryCount'),
                    'pauseAfterInteractionSeconds'  => $this->ReadPropertyInteger(
                        'PauseAfterInteractionSeconds'
                    ),
                    'transitionMilliseconds'        => $this->ReadPropertyInteger(
                        'TransitionMilliseconds'
                    ),
                    'fitMode'                       => $this->ReadPropertyString('FitMode'),
                    'showTitles'                    => $this->ReadPropertyBoolean('ShowTitles'),
                    'showDots'                      => $this->ReadPropertyBoolean('ShowDots'),
                    'showArrows'                    => $this->ReadPropertyBoolean('ShowArrows'),
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            )
        );
    }

    private function mimeTypeFromMediaFile(string $mediaFile): string
    {
        $extension = strtolower((string) pathinfo($mediaFile, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'bmp'         => 'image/bmp',
            default       => throw new InvalidArgumentException(
                'Configured media file has an unsupported image extension.'
            ),
        };
    }

    /** @param array<string, mixed> $request */
    private function readRequestID(array $request): string
    {
        $requestID = $request['requestID'] ?? '';
        if (!is_string($requestID) || !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $requestID)) {
            throw new InvalidArgumentException('Invalid media request ID.');
        }

        return $requestID;
    }

    /** @param array<string, mixed> $request */
    private function readRequestIndex(array $request): int
    {
        $index = $request['index'] ?? null;
        if (!is_int($index) || $index < 0) {
            throw new InvalidArgumentException('Invalid media request index.');
        }

        return $index;
    }

    /** @param array<string, mixed> $message */
    private function encodeMessage(array $message): string
    {
        return json_encode(
            $message,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
