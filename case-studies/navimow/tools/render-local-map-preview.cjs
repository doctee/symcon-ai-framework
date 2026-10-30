const childProcess = require('child_process');
const fs = require('fs');
const path = require('path');
const {chromium} = require('playwright');

function readArguments(argv)
{
    const options = {};
    argv.forEach((argument) => {
        const match = argument.match(/^--([a-z-]+)=(.+)$/);
        if (!match) {
            throw new Error('Unknown argument: ' + argument);
        }
        options[match[1]] = match[2];
    });
    if (!options.output) {
        throw new Error('--output is required.');
    }
    return options;
}

function renderScene(repositoryRoot, scenePath)
{
    const rendererPath = path.join(
        repositoryRoot,
        'case-studies/navimow/distribution/libs/Navimow/LocalMapSvgRenderer.php'
    );
    if (scenePath) {
        const phpCode = [
            'require_once $argv[1];',
            '$decoded = json_decode(file_get_contents($argv[2]), true, 64, JSON_THROW_ON_ERROR);',
            '$scene = isset($decoded["scene"]) ? $decoded["scene"] : $decoded;',
            'echo \\Navimow\\LocalMapSvgRenderer::render($scene);',
        ].join(' ');
        return childProcess.execFileSync(
            'php',
            ['-r', phpCode, rendererPath, scenePath],
            {encoding: 'utf8'}
        );
    }

    const prototypePath = path.join(
        repositoryRoot,
        'case-studies/navimow/tests/local-map-scene-prototype.php'
    );
    const phpCode = [
        'ob_start();',
        'require $argv[1];',
        'ob_end_clean();',
        'require_once $argv[2];',
        'echo \\Navimow\\LocalMapSvgRenderer::render($scene);',
    ].join(' ');
    return childProcess.execFileSync(
        'php',
        ['-r', phpCode, prototypePath, rendererPath],
        {encoding: 'utf8'}
    );
}

function prepareScene(scenePath, labelMapPath, outputRoot)
{
    if (!scenePath || !labelMapPath) {
        return scenePath;
    }
    const decoded = JSON.parse(fs.readFileSync(scenePath, 'utf8'));
    const labelMap = JSON.parse(fs.readFileSync(labelMapPath, 'utf8'));
    const scene = decoded.scene || decoded;
    if (!scene || !Array.isArray(scene.zones) || !labelMap
        || Array.isArray(labelMap) || typeof labelMap !== 'object') {
        throw new Error('Scene or label map is invalid.');
    }
    scene.zones.forEach((zone) => {
        if (zone && typeof zone.label === 'string'
            && typeof labelMap[zone.label] === 'string') {
            zone.label = labelMap[zone.label];
        }
    });
    const preparedPath = path.join(outputRoot, 'preview-scene.local.json');
    fs.writeFileSync(preparedPath, JSON.stringify({scene}, null, 2) + '\n');
    return preparedPath;
}

function sceneStatistics(scenePath)
{
    if (!scenePath) {
        return [
            {zoneId: 101, label: 'Zone A', recencyDays: 0, recencyState: 1,
                latestRunCoveragePercent: null, latestRunEndedAt: 1725192240,
                passProgressPercent: 21, observedArea: 294.89,
                passIsCurrent: true, passObservedAt: 1725278640,
                weekEstimatedArea: null},
            {zoneId: 102, label: 'Zone B', recencyDays: 8, recencyState: 2,
                passProgressPercent: 38, passCoveragePercent: 26.8,
                observedArea: 532.8, passObservedAt: 1793033995,
                weekEstimatedArea: 746.6},
            {zoneId: 103, label: 'Zone C', recencyDays: 15, recencyState: 3},
        ];
    }

    const decoded = JSON.parse(fs.readFileSync(scenePath, 'utf8'));
    const scene = decoded.scene || decoded;
    return (Array.isArray(scene.zones) ? scene.zones : [])
        .map((zone, index) => ({zone, index}))
        .filter((entry) => entry.zone && entry.zone.zoneKey && entry.zone.label)
        .slice(0, 3)
        .map((entry, index) => ({
            zoneId: Number.isInteger(entry.zone.zoneId)
                ? entry.zone.zoneId
                : entry.index + 1,
            label: entry.zone.label,
            recencyDays: [0, 8, 15][index],
            recencyState: [1, 2, 3][index],
            latestRunCoveragePercent: index === 0 ? 47.0 : null,
            passProgressPercent: index === 1 ? 33.1 : null,
            observedArea: index === 1 ? 192.1 : null,
        }));
}

function assembleHtml(repositoryRoot, svg, statistics)
{
    const deviceRoot = path.join(
        repositoryRoot,
        'case-studies/navimow/distribution/NavimowDevice'
    );
    const css = fs.readFileSync(path.join(deviceRoot, 'local-map.css'), 'utf8');
    const script = fs.readFileSync(path.join(deviceRoot, 'local-map.js'), 'utf8');
    const template = fs.readFileSync(path.join(deviceRoot, 'local-map.html'), 'utf8');
    const payload = {
        action: 'render',
        svg,
        theme: 'dark',
        status: 'fresh',
        analytics: {
            geometryKey: 'offline-preview',
            state: 'available',
            zones: statistics,
        },
        statistics,
    };
    return template
        .replace('/* SAEF_NAVIMOW_MAP_STYLE */', css)
        .replace('/* SAEF_NAVIMOW_MAP_SCRIPT */', script)
        .replace('/* SAEF_NAVIMOW_MAP_BOOTSTRAP */', JSON.stringify(payload));
}

function rounded(value)
{
    return Math.round(value * 1000) / 1000;
}

const options = readArguments(process.argv.slice(2));
const repositoryRoot = path.resolve(__dirname, '../../..');
const outputRoot = path.resolve(options.output);
const scenePath = options.scene ? path.resolve(options.scene) : null;
const labelMapPath = options['label-map']
    ? path.resolve(options['label-map'])
    : null;
if (scenePath && !fs.existsSync(scenePath)) {
    throw new Error('Scene does not exist: ' + scenePath);
}
if (labelMapPath && !fs.existsSync(labelMapPath)) {
    throw new Error('Label map does not exist: ' + labelMapPath);
}
fs.mkdirSync(outputRoot, {recursive: true});

const preparedScenePath = prepareScene(scenePath, labelMapPath, outputRoot);
const svg = renderScene(repositoryRoot, preparedScenePath);
const statistics = sceneStatistics(preparedScenePath);
const html = assembleHtml(repositoryRoot, svg, statistics);
fs.writeFileSync(path.join(outputRoot, 'preview.html'), html);

const viewports = [
    {name: 'desktop', width: 1280, height: 720, scale: 1, touch: false},
    {name: 'ipad-observed', width: 590, height: 490, scale: 2, touch: true},
    {name: 'iphone-portrait', width: 390, height: 844, scale: 3, touch: true},
    {name: 'iphone-narrow', width: 375, height: 667, scale: 2, touch: true},
    {name: 'phone-compact', width: 320, height: 568, scale: 2, touch: true},
];

(async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: process.env.PLAYWRIGHT_CHROME
            || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    });
    const results = [];
    try {
        for (const viewport of viewports) {
            const context = await browser.newContext({
                viewport: {width: viewport.width, height: viewport.height},
                deviceScaleFactor: viewport.scale,
                hasTouch: viewport.touch,
                isMobile: viewport.touch,
            });
            const page = await context.newPage();
            await page.setContent(html, {waitUntil: 'load'});
            await page.waitForFunction(() => {
                const map = document.querySelector('[data-map-stage] > svg');
                const legend = document.querySelector('.legend');
                return map && legend && legend.getAttribute('transform').includes('scale(');
            });
            await page.evaluate(() => new Promise(requestAnimationFrame));

            const measurement = await page.evaluate(() => {
                const navigation = document.querySelector('.nav-map__navigation');
                const buttons = [...navigation.querySelectorAll('button')];
                const statistics = document.querySelector('[data-statistics]');
                const rows = [...statistics.children];
                const navRect = navigation.getBoundingClientRect();
                const buttonRects = buttons.map((button) => button.getBoundingClientRect());
                const rowChecks = rows.map((row) => {
                    const rowRect = row.getBoundingClientRect();
                    const children = [...row.children].map((child) => {
                        const rect = child.getBoundingClientRect();
                        const style = getComputedStyle(child);
                        return {
                            left: rect.left,
                            right: rect.right,
                            top: rect.top,
                            bottom: rect.bottom,
                            overflowX: style.overflowX,
                            textOverflow: style.textOverflow,
                            whiteSpace: style.whiteSpace,
                            fits: child.scrollWidth <= child.clientWidth + 1,
                        };
                    });
                    return {
                        zoneId: row.dataset.zoneId,
                        text: row.textContent,
                        title: row.querySelector('strong').textContent,
                        zoneColor: row.style.getPropertyValue('--nav-zone-color'),
                        singleStatusLines: row.querySelectorAll('.nav-map__status-line').length === 2
                            && Array.from(row.querySelectorAll('.nav-map__status-line')).every(
                                (line) => getComputedStyle(line).whiteSpace === 'nowrap'
                                    && line.scrollWidth <= line.clientWidth + 1
                                    && line.getBoundingClientRect().height
                                        <= parseFloat(getComputedStyle(line).lineHeight) + 1
                            ),
                        statusFontSizes: Array.from(row.querySelectorAll('.nav-map__status-line'))
                            .map((line) => parseFloat(getComputedStyle(line).fontSize)),
                        visible: rowRect.left >= statistics.getBoundingClientRect().left
                            && rowRect.right <= statistics.getBoundingClientRect().right + 0.5,
                        inside: children.every((rect) => (
                            rect.left >= rowRect.left - 0.5
                            && rect.right <= rowRect.right + 0.5
                            && rect.top >= rowRect.top - 0.5
                            && rect.bottom <= rowRect.bottom + 0.5
                        )),
                        ellipsisBounded: children.every((rect) => (
                            (rect.overflowX === 'hidden'
                                && rect.textOverflow === 'ellipsis'
                                && rect.whiteSpace === 'nowrap')
                            || (rect.whiteSpace === 'nowrap' && rect.fits)
                        )),
                    };
                });
                const zoneCenters = rowChecks.map((row) => {
                    const zone = document.querySelector(
                        '.zone[data-zone-id="' + CSS.escape(row.zoneId) + '"]'
                    );
                    if (!zone) {
                        return null;
                    }
                    const box = zone.getBBox();
                    return {
                        centerX: box.x + box.width / 2,
                        color: zone.getAttribute('stroke') || '',
                    };
                });
                return {
                    navigation: {
                        top: navRect.top,
                        left: navRect.left,
                        width: navRect.width,
                        height: navRect.height,
                    },
                    buttonTops: buttonRects.map((rect) => rect.top),
                    followButton: (() => {
                        const rect = document.querySelector('[data-follow]')
                            .getBoundingClientRect();
                        return {
                            left: rect.left,
                            top: rect.top,
                            width: rect.width,
                        };
                    })(),
                    buttonCount: buttons.length,
                    hasZoneSelector: Boolean(document.querySelector('[data-zone]')),
                    statisticsHeight: statistics.getBoundingClientRect().height,
                    statisticsScrolls: statistics.scrollWidth > statistics.clientWidth,
                    rows: rowChecks,
                    zoneCenters,
                };
            });
            const matchedZones = measurement.zoneCenters.every(
                (zone) => zone !== null
            );
            const expectedButtonTop = 9;
            const activationX = measurement.followButton.left
                + measurement.followButton.width / 2;
            const activationY = measurement.followButton.top + 0.5;
            if (viewport.touch) {
                await page.touchscreen.tap(activationX, activationY);
            } else {
                await page.mouse.click(activationX, activationY);
            }
            const activatedAtBoundary = await page.locator('[data-follow]')
                .getAttribute('aria-pressed') === 'true';
            if (viewport.touch) {
                await page.touchscreen.tap(activationX, activationY);
            } else {
                await page.mouse.click(activationX, activationY);
            }
            const deactivatedAtBoundary = await page.locator('[data-follow]')
                .getAttribute('aria-pressed') === 'false';
            const checks = {
                fourControls: measurement.buttonCount === 4,
                zoneSelectorRemoved: measurement.hasZoneSelector === false,
                toolbarInset:
                    Math.abs(measurement.navigation.top - 6) <= 0.5
                    && Math.abs(measurement.navigation.left - 16) <= 0.5,
                buttonsAtToolbarTop: measurement.buttonTops.every(
                    (top) => Math.abs(top - expectedButtonTop) <= 0.5
                ),
                boundaryPointerActivation:
                    activatedAtBoundary && deactivatedAtBoundary,
                compactWidth: measurement.navigation.width <= 160,
                statisticsInsideCards: measurement.rows.every((row) => row.inside),
                unknownCoverageExplicit: preparedScenePath !== null
                    || measurement.rows.some((row) => (
                        row.zoneId === '101'
                        && row.text.includes('Woche –')
                        && row.text.includes('aktuell')
                        && row.text.includes('21 %')
                        && !row.text.includes('(≈')
                    )),
                twoSingleStatusLines: measurement.rows.every((row) => row.singleStatusLines),
                allZonesVisible: measurement.rows.length === 3
                    && measurement.rows.every((row) => row.visible)
                    && !measurement.statisticsScrolls,
                completedPassWithCoverage: preparedScenePath !== null
                    || measurement.rows.some((row) => row.zoneId === '102'
                        && row.text.includes('26.10.')
                        && row.text.includes('38 %')
                        && !row.text.includes('26,8')
                        && row.text.includes('533 m²')
                        && row.text.includes('747 m²')),
                readableStatusFont: measurement.rows.every((row) =>
                    row.statusFontSizes.every((size) => size >= 12)),
                statisticsOverflowBounded: measurement.rows.every(
                    (row) => row.ellipsisBounded
                ),
                statisticsFollowMapOrder: matchedZones
                    && measurement.zoneCenters.every((zone, index, zones) => (
                        index === 0 || zones[index - 1].centerX <= zone.centerX
                    )),
                statisticsUseMapColors: matchedZones
                    && measurement.rows.every((row, index) => (
                        row.zoneColor === measurement.zoneCenters[index].color
                    )),
            };
            checks.noticeDoesNotCoverToolbar = await page.evaluate(() => {
                const status = document.querySelector('[data-status]');
                const previous = status.textContent;
                status.textContent = 'Positionsdaten veraltet';
                const a = status.getBoundingClientRect();
                const b = document.querySelector('.nav-map__navigation').getBoundingClientRect();
                status.textContent = previous;
                return a.bottom <= b.top || a.top >= b.bottom
                    || a.right <= b.left || a.left >= b.right;
            });
            await page.locator('[data-fit]').click();
            await page.evaluate(() => new Promise(requestAnimationFrame));
            await page.screenshot({
                path: path.join(outputRoot, viewport.name + '.png'),
                fullPage: true,
            });
            await page.locator('[data-statistics]').screenshot({
                path: path.join(outputRoot, viewport.name + '-footer.png'),
            });
            const selected = page.locator('.nav-map__zone-stat[data-zone-id="102"]');
            if (viewport.touch) {
                await selected.tap();
            } else {
                await selected.focus();
                await page.keyboard.press('Enter');
            }
            checks.detailsOpen = await page.locator('dialog').evaluate((dialog) => dialog.open);
            const detailsText = await page.locator('[data-details-values]').textContent();
            checks.exactDetails = preparedScenePath !== null
                || (detailsText.includes('532,8 m²') && detailsText.includes('26,8 %')
                    && detailsText.includes('746,6 m²'));
            checks.dialogFits = await page.locator('dialog').evaluate((dialog) => {
                const rect = dialog.getBoundingClientRect();
                return rect.left >= 0 && rect.right <= window.innerWidth
                    && rect.top >= 0 && rect.bottom <= window.innerHeight
                    && dialog.scrollWidth <= dialog.clientWidth;
            });
            await page.screenshot({
                path: path.join(outputRoot, viewport.name + '-details.png'), fullPage: true,
            });
            await page.keyboard.press('Escape');
            checks.escapeCloses = await page.locator('dialog').evaluate((dialog) => !dialog.open);
            checks.focusRestored = await selected.evaluate((button) => document.activeElement === button);
            await selected.click();
            const updated = statistics.map((zone) => ({
                ...zone, observedArea: 12345.67, weekEstimatedArea: 123456.78,
                passProgressPercent: 100,
            }));
            await page.evaluate(({svg, statistics}) => {
                window.handleMessage({action: 'render', svg, theme: 'light', status: 'fresh',
                    statistics, analytics: {geometryKey: 'offline-preview', zones: statistics}});
            }, {svg, statistics: updated});
            checks.openDetailsRefresh = await page.locator('[data-details-values]')
                .textContent().then((value) => value.includes('12.345,67 m²')
                    && value.includes('123.456,78 m²'));
            await page.locator('[data-details-close]').click();
            await page.waitForFunction(() =>
                document.activeElement.dataset.zoneId === '102');
            checks.closeButtonWorks = await page.locator('dialog').evaluate((dialog) => !dialog.open);
            checks.focusAfterRefresh = await page.locator(
                '.nav-map__zone-stat[data-zone-id="102"]'
            ).evaluate((button) => document.activeElement === button);
            for (const width of [320, 1280, viewport.width]) {
                await page.setViewportSize({width, height: viewport.height});
                await page.evaluate(() => new Promise((resolve) => {
                    requestAnimationFrame(() => requestAnimationFrame(resolve));
                }));
                checks['largeValuesFitAt' + width] = await page.locator('[data-statistics]')
                    .evaluate((panel) => panel.scrollWidth <= panel.clientWidth
                        && Array.from(panel.querySelectorAll('.nav-map__status-line'))
                            .every((line) => {
                                const range = document.createRange();
                                range.selectNodeContents(line);
                                return range.getBoundingClientRect().width <= line.clientWidth
                                    && getComputedStyle(line).whiteSpace === 'nowrap'
                                    && parseFloat(getComputedStyle(line).fontSize) >= 12;
                            }));
            }
            await page.locator('.nav-map__zone-stat[data-zone-id="103"]').click();
            checks.unknownIsNotZero = preparedScenePath !== null
                || (await page.locator('[data-details-values]').textContent())
                    .includes('Abdeckung · geschätztNicht ermittelbar');
            await page.mouse.click(1, 1);
            checks.backdropCloses = await page.locator('dialog').evaluate((dialog) => !dialog.open);
            await page.locator('.nav-map__zone-stat[data-zone-id="103"]').click();
            await page.evaluate(() => window.handleMessage({action: 'configurationError'}));
            checks.errorClosesDetails = await page.locator('dialog').evaluate((dialog) => !dialog.open);
            results.push({
                viewport,
                pass: Object.values(checks).every(Boolean),
                checks,
                measurement: {
                    navigation: Object.fromEntries(
                        Object.entries(measurement.navigation)
                            .map(([key, value]) => [key, rounded(value)])
                    ),
                    buttonTops: measurement.buttonTops.map(rounded),
                    statisticsHeight: rounded(measurement.statisticsHeight),
                    statisticsScrolls: measurement.statisticsScrolls,
                    statusFontSizes: measurement.rows.map((row) => row.statusFontSizes),
                },
            });
            await context.close();
        }
    } finally {
        await browser.close();
    }

    const report = {
        formatVersion: 1,
        purpose: 'navimow-local-map-exact-preview',
        source: scenePath ? 'private-scene' : 'synthetic-scene',
        passed: results.filter((result) => result.pass).length,
        failed: results.filter((result) => !result.pass).length,
        results,
    };
    fs.writeFileSync(
        path.join(outputRoot, 'preview-check.json'),
        JSON.stringify(report, null, 2) + '\n'
    );
    process.stdout.write(JSON.stringify(report, null, 2) + '\n');
    if (report.failed !== 0) {
        process.exitCode = 1;
    }
})().catch((error) => {
    process.stderr.write(String(error.stack || error) + '\n');
    process.exitCode = 1;
});
