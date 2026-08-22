<?php

declare(strict_types=1);

class MediaCarousel extends IPSModuleStrict
{
    private const VISUALIZATION_TYPE_HTML = 1;
    private const STATUS_INVALID_CONFIGURATION = 200;
    private const MAX_ITEM_COUNT = 50;
    private const DISPLAY_MAX_WIDTH = 1280;
    private const DISPLAY_JPEG_QUALITY = 76;
    private const PREVIEW_MAX_WIDTH = 480;
    private const PREVIEW_JPEG_QUALITY = 58;
    private const SOURCE_CATEGORY = 'category';
    private const SOURCE_LIST = 'list';

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

        $this->RegisterPropertyString('SourceMode', self::SOURCE_LIST);
        $this->RegisterPropertyString('MediaItems', '[]');
        $this->RegisterPropertyInteger('SourceCategoryID', 0);
        $this->RegisterPropertyInteger('CategoryItemLimit', 10);
        $this->RegisterPropertyBoolean('CategoryNewestFirst', true);
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
        $this->RegisterAttributeInteger('RegisteredCategoryID', 0);

        $visualizationType = defined('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN')
            ? (int) constant('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN')
            : self::VISUALIZATION_TYPE_HTML;
        $this->SetVisualizationType($visualizationType);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->applyConfiguration(false);
    }

    private function applyConfiguration(bool $includeInitialPreview): void
    {
        $this->unregisterConfiguredSources();

        try {
            $this->validatePresentationConfiguration();
            $items = $this->resolveMediaItems();
            $this->registerConfiguredSources($items);

            $this->SetStatus($items === [] ? IS_INACTIVE : IS_ACTIVE);
            $this->UpdateVisualizationValue(
                $this->encodeMessage($this->createBootstrapMessage($includeInitialPreview))
            );
        } catch (Throwable $exception) {
            $this->WriteAttributeString('RegisteredMediaIDs', '[]');
            $this->WriteAttributeInteger('RegisteredCategoryID', 0);
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
            $this->applyConfiguration(true);

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
            $this->validatePresentationConfiguration();
            $items = $this->resolveMediaItems();
            $configurationRevision = $this->configurationRevision($items);

            if ($this->readRequestConfigurationRevision($request) !== $configurationRevision) {
                $this->UpdateVisualizationValue(
                    $this->encodeMessage($this->createBootstrapMessage(true))
                );

                return;
            }

            if (!array_key_exists($index, $items)) {
                throw new OutOfRangeException('Requested media index is outside the configured sequence.');
            }

            $this->UpdateVisualizationValue(
                $this->encodeMessage(
                    $this->createMediaMessage(
                        $items,
                        $index,
                        $requestID,
                        $configurationRevision
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
        return match ($this->ReadPropertyString('SourceMode')) {
            self::SOURCE_LIST     => $this->resolveExplicitMediaItems(),
            self::SOURCE_CATEGORY => $this->resolveCategoryMediaItems(),
            default               => throw new InvalidArgumentException(
                'SourceMode must be list or category.'
            ),
        };
    }

    /**
     * @return list<array{mediaID: int, title: string, mimeType: string}>
     */
    private function resolveExplicitMediaItems(): array
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
        $enabledRows = 0;

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
            $enabledRows++;

            $mediaID = $row['MediaID'] ?? null;
            if (!is_int($mediaID) || $mediaID <= 0) {
                throw new InvalidArgumentException(
                    sprintf('MediaItems row %d has no positive MediaID.', $rowIndex)
                );
            }
            if (isset($seenMediaIDs[$mediaID])) {
                throw new InvalidArgumentException('Duplicate MediaID is not allowed.');
            }
            $seenMediaIDs[$mediaID] = true;
            if (!IPS_MediaExists($mediaID)) {
                $this->SendDebug(
                    'Missing configured media skipped',
                    sprintf('MediaItems row %d no longer exists.', $rowIndex),
                    0
                );

                continue;
            }

            $title = $row['Title'] ?? '';
            if (!is_string($title)) {
                throw new InvalidArgumentException('Media title must be text.');
            }
            $title = trim($title);
            if (strlen($title) > 120) {
                throw new InvalidArgumentException('Media title exceeds 120 bytes.');
            }
            $items[] = $this->createMediaItem($mediaID, $title);
        }

        if ($enabledRows > 0 && $items === []) {
            throw new InvalidArgumentException('No configured media object currently exists.');
        }

        return $items;
    }

    /**
     * @return list<array{mediaID: int, title: string, mimeType: string}>
     */
    private function resolveCategoryMediaItems(): array
    {
        $categoryID = $this->ReadPropertyInteger('SourceCategoryID');
        if ($categoryID <= 0 || !IPS_CategoryExists($categoryID)) {
            throw new InvalidArgumentException('SourceCategoryID must identify an existing category.');
        }

        $candidates = [];
        foreach (IPS_GetChildrenIDs($categoryID) as $childID) {
            if ($childID <= 0 || !IPS_MediaExists($childID)) {
                continue;
            }

            try {
                $item = $this->createMediaItem($childID, '');
                $object = IPS_GetObject($childID);
                $candidates[] = [
                    'item'     => $item,
                    'position' => (int) ($object['ObjectPosition'] ?? 0),
                ];
            } catch (Throwable $exception) {
                $this->SendDebug('Category child skipped', $exception->getMessage(), 0);
            }
        }

        $newestFirst = $this->ReadPropertyBoolean('CategoryNewestFirst');
        usort(
            $candidates,
            static function (array $left, array $right) use ($newestFirst): int {
                $comparison = [$left['position'], $left['item']['mediaID']]
                    <=> [$right['position'], $right['item']['mediaID']];

                return $newestFirst ? -$comparison : $comparison;
            }
        );

        $limit = $this->ReadPropertyInteger('CategoryItemLimit');
        if ($limit < 1 || $limit > self::MAX_ITEM_COUNT) {
            throw new InvalidArgumentException('CategoryItemLimit is outside the supported range.');
        }
        $items = array_map(
            static fn (array $candidate): array => $candidate['item'],
            array_slice($candidates, 0, $limit)
        );
        if ($items === []) {
            throw new InvalidArgumentException('Source category contains no supported image media.');
        }

        return $items;
    }

    /** @return array{mediaID: int, title: string, mimeType: string} */
    private function createMediaItem(int $mediaID, string $title): array
    {
        $media = IPS_GetMedia($mediaID);
        if ($media['MediaType'] !== MEDIATYPE_IMAGE) {
            throw new InvalidArgumentException('Configured object is not image media.');
        }

        $mediaFile = $media['MediaFile'];
        if ($mediaFile === '') {
            throw new InvalidArgumentException('Configured media object has no media file.');
        }

        $title = trim($title);
        if ($title === '') {
            $title = IPS_GetName($mediaID);
        }
        if (strlen($title) > 120) {
            throw new InvalidArgumentException('Media title exceeds 120 bytes.');
        }

        return [
            'mediaID'  => $mediaID,
            'title'    => $title,
            'mimeType' => $this->mimeTypeFromMediaFile($mediaFile),
        ];
    }

    private function validatePresentationConfiguration(): void
    {
        $this->assertIntegerRange('LoopSeconds', 3, 120);
        $this->assertIntegerRange('LoadTimeoutSeconds', 2, 30);
        $this->assertIntegerRange('RetryCount', 0, 5);
        $this->assertIntegerRange('PauseAfterInteractionSeconds', 0, 120);
        $this->assertIntegerRange('TransitionMilliseconds', 100, 1000);
        $this->assertIntegerRange('MaxMediaMegabytes', 1, 20);
        $this->assertIntegerRange('CategoryItemLimit', 1, self::MAX_ITEM_COUNT);

        if (!in_array($this->ReadPropertyString('FitMode'), ['cover', 'contain'], true)) {
            throw new InvalidArgumentException('FitMode must be cover or contain.');
        }
        $supportedSourceMode = in_array(
            $this->ReadPropertyString('SourceMode'),
            [self::SOURCE_LIST, self::SOURCE_CATEGORY],
            true
        );
        if (!$supportedSourceMode) {
            throw new InvalidArgumentException('SourceMode must be list or category.');
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
    private function registerConfiguredSources(array $items): void
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

        if ($this->ReadPropertyString('SourceMode') === self::SOURCE_CATEGORY) {
            $categoryID = $this->ReadPropertyInteger('SourceCategoryID');
            $this->RegisterReference($categoryID);
            $this->WriteAttributeInteger('RegisteredCategoryID', $categoryID);
        }
    }

    private function unregisterConfiguredSources(): void
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

        $categoryID = $this->ReadAttributeInteger('RegisteredCategoryID');
        if ($categoryID > 0) {
            $this->UnregisterReference($categoryID);
        }
        $this->WriteAttributeInteger('RegisteredCategoryID', 0);
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
    private function createBootstrapMessage(bool $includeInitialPreview): array
    {
        $this->validatePresentationConfiguration();
        $items = $this->resolveMediaItems();
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

        if ($includeInitialPreview && $items !== []) {
            $message['initialMedia'] = $this->createInitialMediaMessage(
                $items,
                0,
                $configurationRevision
            );
        }

        return $message;
    }

    /**
     * @param list<array{mediaID: int, title: string, mimeType: string}> $items
     * @return array<string, bool|int|string>
     */
    private function createMediaMessage(
        array $items,
        int $index,
        string $requestID,
        string $configurationRevision
    ): array {
        $content = $this->readMediaContent($items[$index]['mediaID']);

        try {
            return $this->createRenderedMediaMessage(
                $index,
                $requestID,
                $configurationRevision,
                $content,
                self::DISPLAY_MAX_WIDTH,
                self::DISPLAY_JPEG_QUALITY,
                false
            );
        } catch (Throwable $exception) {
            $this->SendDebug('Display image generation failed', $exception->getMessage(), 0);

            return $this->createOriginalMediaMessage(
                $items,
                $index,
                $requestID,
                $configurationRevision,
                $content
            );
        }
    }

    /**
     * @param list<array{mediaID: int, title: string, mimeType: string}> $items
     * @return array<string, bool|int|string>
     */
    private function createOriginalMediaMessage(
        array $items,
        int $index,
        string $requestID,
        string $configurationRevision,
        ?string $content = null
    ): array {
        $item = $items[$index];
        $content ??= $this->readMediaContent($item['mediaID']);

        return [
            'action'                => 'media',
            'configurationRevision' => $configurationRevision,
            'requestID'             => $requestID,
            'index'                 => $index,
            'contentRevision'       => hash('sha256', $content),
            'source'                => 'data:' . $item['mimeType'] . ';base64,' . $content,
            'preview'               => false,
        ];
    }

    /**
     * @param list<array{mediaID: int, title: string, mimeType: string}> $items
     * @return array<string, bool|int|string>
     */
    private function createInitialMediaMessage(
        array $items,
        int $index,
        string $configurationRevision
    ): array {
        try {
            return $this->createPreviewMediaMessage($items, $index, $configurationRevision);
        } catch (Throwable $exception) {
            $this->SendDebug('Preview generation failed', $exception->getMessage(), 0);

            return $this->createMediaMessage(
                $items,
                $index,
                'initial-fallback',
                $configurationRevision
            );
        }
    }

    /**
     * @param list<array{mediaID: int, title: string, mimeType: string}> $items
     * @return array<string, bool|int|string>
     */
    private function createPreviewMediaMessage(
        array $items,
        int $index,
        string $configurationRevision
    ): array {
        $content = $this->readMediaContent($items[$index]['mediaID']);

        return $this->createRenderedMediaMessage(
            $index,
            'initial-preview',
            $configurationRevision,
            $content,
            self::PREVIEW_MAX_WIDTH,
            self::PREVIEW_JPEG_QUALITY,
            true
        );
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function createRenderedMediaMessage(
        int $index,
        string $requestID,
        string $configurationRevision,
        string $content,
        int $maximumWidth,
        int $jpegQuality,
        bool $preview
    ): array {
        foreach (
            [
                'imagecreatefromstring',
                'imagecreatetruecolor',
                'imagecopyresampled',
                'imagecolorallocate',
                'imagefill',
                'imagejpeg',
            ] as $requiredFunction
        ) {
            if (!function_exists($requiredFunction)) {
                throw new RuntimeException('GD preview support is unavailable.');
            }
        }

        $binary = base64_decode($content, true);
        if ($binary === false) {
            throw new RuntimeException('Media content is not valid base64.');
        }

        $sourceImage = @imagecreatefromstring($binary);
        if ($sourceImage === false) {
            throw new RuntimeException('Media content cannot be decoded for preview generation.');
        }

        $previewImage = null;
        $previewBytes = '';
        $bufferLevel = ob_get_level();

        try {
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            $previewWidth = min($maximumWidth, $sourceWidth);
            $previewHeight = max(
                1,
                (int) round($sourceHeight * ($previewWidth / $sourceWidth))
            );
            $previewImage = imagecreatetruecolor($previewWidth, $previewHeight);
            if ($previewImage === false) {
                throw new RuntimeException('Preview canvas cannot be created.');
            }

            $background = imagecolorallocate($previewImage, 0, 0, 0);
            imagefill($previewImage, 0, 0, $background);
            $resampled = imagecopyresampled(
                $previewImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $previewWidth,
                $previewHeight,
                $sourceWidth,
                $sourceHeight
            );
            if (!$resampled) {
                throw new RuntimeException('Preview resampling failed.');
            }

            ob_start();
            if (!imagejpeg($previewImage, null, $jpegQuality)) {
                throw new RuntimeException('Preview encoding failed.');
            }
            $previewBytes = ob_get_clean();
            if ($previewBytes === '') {
                throw new RuntimeException('Preview encoding returned no data.');
            }
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }

        return [
            'action'                => 'media',
            'configurationRevision' => $configurationRevision,
            'requestID'             => $requestID,
            'index'                 => $index,
            'contentRevision'       => hash(
                'sha256',
                $content . ':render:' . $maximumWidth . ':' . $jpegQuality
            ),
            'source'                => 'data:image/jpeg;base64,' . base64_encode($previewBytes),
            'preview'               => $preview,
        ];
    }

    private function readMediaContent(int $mediaID): string
    {
        $content = IPS_GetMediaContent($mediaID);
        if ($content === '') {
            throw new RuntimeException('Media content is empty.');
        }

        $estimatedBytes = intdiv(strlen($content) * 3, 4);
        $maximumBytes = $this->ReadPropertyInteger('MaxMediaMegabytes') * 1024 * 1024;
        if ($estimatedBytes > $maximumBytes) {
            throw new LengthException('Media content exceeds MaxMediaMegabytes.');
        }

        return $content;
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
                    'sourceMode'                    => $this->ReadPropertyString('SourceMode'),
                    'sourceCategoryID'              => $this->ReadPropertyInteger(
                        'SourceCategoryID'
                    ),
                    'categoryItemLimit'             => $this->ReadPropertyInteger(
                        'CategoryItemLimit'
                    ),
                    'categoryNewestFirst'           => $this->ReadPropertyBoolean(
                        'CategoryNewestFirst'
                    ),
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

    /** @param array<string, mixed> $request */
    private function readRequestConfigurationRevision(array $request): string
    {
        $revision = $request['configurationRevision'] ?? '';
        if (!is_string($revision) || preg_match('/^[a-f0-9]{64}$/D', $revision) !== 1) {
            throw new InvalidArgumentException('Invalid media configuration revision.');
        }

        return $revision;
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
