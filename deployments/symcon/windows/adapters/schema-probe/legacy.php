<?php

declare(strict_types=1);

// Inert serializer fixture: no camera access, timers, attributes or visualization.
// SourceCategoryID mirrors the legacy scalar default; it is never used as an API target.
class SAEFMediaCarouselSchemaProbe extends IPSModuleStrict
{
    private const SOURCE_LIST = 'list';

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
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
    }
}
