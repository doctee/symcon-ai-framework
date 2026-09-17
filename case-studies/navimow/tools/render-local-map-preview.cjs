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
                latestRunCoveragePercent: 47.0, weekEstimatedArea: 120.5},
            {zoneId: 102, label: 'Zone B', recencyDays: 8, recencyState: 2,
                passProgressPercent: 33.1, observedArea: 192.1},
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
                        };
                    });
                    return {
                        zoneId: row.dataset.zoneId,
                        title: row.querySelector('strong').textContent,
                        zoneColor: row.style.getPropertyValue('--nav-zone-color'),
                        inside: children.every((rect) => (
                            rect.left >= rowRect.left - 0.5
                            && rect.right <= rowRect.right + 0.5
                            && rect.top >= rowRect.top - 0.5
                            && rect.bottom <= rowRect.bottom + 0.5
                        )),
                        ellipsisBounded: children.every((rect) => (
                            rect.overflowX === 'hidden'
                            && rect.textOverflow === 'ellipsis'
                            && rect.whiteSpace === 'nowrap'
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
            const expectedButtonTop = viewport.touch ? 46 : 48;
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
                frameAboveInteractionBoundary:
                    measurement.navigation.top < expectedButtonTop,
                buttonsAtInteractionBoundary: measurement.buttonTops.every(
                    (top) => Math.abs(top - expectedButtonTop) <= 0.5
                ),
                boundaryPointerActivation:
                    activatedAtBoundary && deactivatedAtBoundary,
                compactWidth: measurement.navigation.width <= 160,
                statisticsInsideCards: measurement.rows.every((row) => row.inside),
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
            await page.screenshot({
                path: path.join(outputRoot, viewport.name + '.png'),
                fullPage: true,
            });
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
