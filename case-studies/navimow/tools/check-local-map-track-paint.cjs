const fs = require('fs');
const path = require('path');
const {execFileSync} = require('child_process');
const {chromium} = require('playwright');

const output = process.argv[2];
if (!output) throw new Error('An output directory is required.');
fs.mkdirSync(output, {recursive: true});
const root = path.resolve(__dirname, '../../..');
const scene = {
    formatVersion: 1,
    coordinateFrame: 'navimow-local-map-candidate',
    viewport: {minimumX: -10, minimumY: -10, maximumX: 110, maximumY: 70, width: 120, height: 80},
    revision: {geometryKey: 'synthetic-track-paint'},
    zones: Array.from({length: 6}, (_, i) => ({
        zoneId: i + 1, label: 'Zone ' + (i + 1),
        ring: [[i * 18, 0], [i * 18 + 15, 0], [i * 18 + 15, 60], [i * 18, 60], [i * 18, 0]],
    })),
    obstacles: [],
    path: {segments: [{points: [
        {localX: -5, localY: 20, receivedAt: 100, attribution: {source: 'geometry'}},
        {localX: 108, localY: 20, receivedAt: 100, attribution: {source: 'geometry'}},
    ]}]},
};
const colors = {
    dark: {background: '#171b1f', fills: ['#243a52', '#24483a', '#4b3d24', '#4b2d40', '#333554', '#204742'],
        tracks: ['#8396a8', '#839e90', '#a79b80', '#a38a9a', '#8f90a6', '#819e99'], outside: '#939a9d'},
    light: {background: '#f8fafc', fills: ['#dbeafe', '#dcfce7', '#fef3c7', '#fce7f3', '#e0e7ff', '#ccfbf1'],
        tracks: ['#697d91', '#6d8978', '#958565', '#96788b', '#777992', '#658880'], outside: '#768084'},
};
const rgb = (hex) => [1, 3, 5].map((start) => parseInt(hex.slice(start, start + 2), 16));
const mix = (a, b, alpha) => a.map((value, i) => value * (1 - alpha) + b[i] * alpha);
function render(theme, source = scene) {
    return execFileSync('php', ['-r',
        'require $argv[1]; echo Navimow\\LocalMapSvgRenderer::render(json_decode(stream_get_contents(STDIN), true), ["theme"=>$argv[2], "showMower"=>false, "hiddenZoneSequences"=>[1,2,3,4,5,6]]);',
        path.join(root, 'case-studies/navimow/distribution/libs/Navimow/LocalMapSvgRenderer.php'), theme,
    ], {input: JSON.stringify(source), encoding: 'utf8'});
}
(async () => {
    const browser = await chromium.launch({headless: true,
        executablePath: process.env.PLAYWRIGHT_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
    const results = [];
    try {
        for (const theme of ['dark', 'light']) {
            const page = await browser.newPage({viewport: {width: 1200, height: 800}});
            await page.setContent(render(theme));
            const samples = await page.evaluate(async () => {
                const svg = document.querySelector('svg');
                svg.querySelector('.legend').remove();
                const img = new Image();
                img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(svg));
                await img.decode();
                const canvas = document.createElement('canvas');
                canvas.width = 1200; canvas.height = 800;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, 1200, 800);
                return [-5, 7, 25, 43, 61, 79, 97].map((x) =>
                    [...ctx.getImageData((x + 10) * 10, 500, 1, 1).data].slice(0, 3));
            });
            const palette = colors[theme];
            samples.forEach((sample, index) => {
                const background = index === 0 ? rgb(palette.background)
                    : mix(rgb(palette.background), rgb(palette.fills[index - 1]), 0.62);
                const expected = mix(background, rgb(index === 0 ? palette.outside : palette.tracks[index - 1]), 0.35);
                if (!sample.every((value, i) => Math.abs(value - expected[i]) <= 3)) {
                    throw new Error(theme + ' paint mismatch ' + index + ': ' + sample + ' expected ' + expected);
                }
            });
            const dense = structuredClone(scene);
            dense.path.segments[0].points = [];
            for (let y = 3; y <= 57; y += 3) {
                const reverse = y % 6 === 0;
                for (const x of (reverse ? [103, 2] : [2, 103])) {
                    dense.path.segments[0].points.push({localX: x, localY: y, receivedAt: 100, attribution: {source: 'geometry'}});
                }
            }
            await page.setContent(render(theme, dense));
            for (const width of [1200, 390]) {
                await page.setViewportSize({width, height: width === 390 ? 600 : 800});
                await page.screenshot({path: path.join(output, theme + '-' + width + '.png')});
            }
            results.push({theme, zonePixels: 6, outsidePixel: true, samples});
            await page.close();
        }
    } finally { await browser.close(); }
    fs.writeFileSync(path.join(output, 'paint-check.json'), JSON.stringify({passed: true, source: 'synthetic', results}, null, 2));
    console.log('Track paint pixels and four screenshots passed.');
})().catch((error) => { console.error(error); process.exitCode = 1; });
