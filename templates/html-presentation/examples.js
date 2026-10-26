/* Synthetic preview only. No Symcon bridge, provider requests or persistence. */
(() => {
    'use strict';
    const preview = document.getElementById('preview');
    const scenarioSelect = document.getElementById('preview-scenario');
    const batteryRows = document.getElementById('battery-rows');
    const lightRows = document.getElementById('light-rows');
    const minuteSelect = document.getElementById('rain-minute');
    let filter = 'all';
    let lights = [true, false, null];

    const batteries = [
        { name: 'Sensor A', value: '8 %', state: 'Schwach', tone: 'warning' },
        { name: 'Sensor B', value: '84 %', state: 'Aktuell', tone: 'normal' },
        { name: 'Sensor mit einem längeren Namen C', value: '—', state: 'Unbekannt', tone: 'unknown' }
    ];
    // Fixed presentation fixture, not a second forecast/interpolation implementation.
    // Colors use NowcastHtmlRenderer::colorForIntensity at its default 0.1 mm/h threshold.
    const rainBlocks = [
        [0, '#4b5563'], [0, '#4b5563'], [0, '#4b5563'],
        [.1, '#60a5fa'], [.25, '#3b82f6'], [.4, '#4ade80'],
        [.6, '#a3e635'], [.8, '#facc15'], [.6, '#a3e635'],
        [.25, '#3b82f6'], [0, '#4b5563'], [0, '#4b5563']
    ];
    const minutes = rainBlocks.flatMap(([value, color]) => Array.from({ length: 5 }, () => ({ value, color })));
    minutes[36] = null;

    function element(tag, className, text) {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (text !== undefined) el.textContent = text;
        return el;
    }

    function notice(id, text) {
        const el = document.getElementById(id);
        el.hidden = !text;
        el.textContent = text;
    }

    function renderBattery(scenario) {
        batteryRows.replaceChildren();
        for (const entry of scenario === 'empty' ? [] : batteries) {
            if (filter === 'attention' && entry.tone === 'normal' && scenario !== 'stale') continue;
            const row = element('tr');
            const name = element('th', '', entry.name);
            name.scope = 'row';
            const value = element('td', 'saef-html__number', entry.value);
            const cell = element('td');
            const status = element('span', 'saef-html__status', scenario === 'stale' ? 'Veraltet' : entry.state);
            status.dataset.tone = scenario === 'stale' ? 'unknown' : entry.tone;
            cell.append(status);
            row.append(name, value, cell);
            batteryRows.append(row);
        }
        document.getElementById('battery-empty').hidden = scenario !== 'empty';
        notice('battery-notice', scenario === 'empty' ? '' : scenario === 'stale' ? '? Veraltete Daten · Angaben sind letzte bekannte Werte.' : '! Ein Gerät braucht eine neue Batterie.');
        document.getElementById('battery-stamp').textContent = scenario === 'stale' ? 'Beispieldaten · letzter Stand vor 2 Tagen' : scenario === 'empty' ? 'Beispieldaten · kein Datenstand vorhanden' : 'Beispieldaten · Stand vor 2 Minuten';
    }

    function renderLights(scenario) {
        lightRows.replaceChildren();
        lights.forEach((value, index) => {
            const row = element('div', 'saef-html__row saef-html__value-row');
            const text = element('div', 'example-light-name');
            text.append(element('span', '', `Raum ${String.fromCharCode(65 + index)}`));
            const known = scenario === 'normal' && value !== null;
            const status = element('span', 'saef-html__status', known ? (value ? 'Ein' : 'Aus') : (scenario === 'stale' ? 'Letzter Stand veraltet' : 'Unbekannt'));
            status.dataset.tone = known ? 'normal' : 'unknown';
            text.append(status);
            const button = element('button', 'saef-html__button example-light-action', value ? 'Ausschalten' : 'Einschalten');
            button.type = 'button';
            button.disabled = !known;
            button.setAttribute('aria-label', `${button.textContent} · Raum ${String.fromCharCode(65 + index)} · nur Beispiel`);
            button.addEventListener('click', () => {
                lights[index] = !lights[index];
                renderLights('normal');
                document.getElementById('light-result').textContent = `Beispiel geändert · Raum ${String.fromCharCode(65 + index)} ${lights[index] ? 'ein' : 'aus'}`;
            });
            row.append(text, button);
            lightRows.append(row);
        });
        notice('light-notice', scenario === 'normal' ? '' : '? Kein aktueller Status · die Vorschau zeigt keine bestätigte Bedienung.');
    }

    function updateMinute() {
        const minute = Number(minuteSelect.value);
        const entry = minutes[minute];
        const prefix = scenarioSelect.value === 'stale' ? 'Letzte Vorhersage · ' : '';
        document.getElementById('rain-detail').textContent = prefix + (entry ? `+${minute} min · ${entry.value.toLocaleString('de-DE', { maximumFractionDigits: 2 })} mm/h` : `+${minute} min · kein Wert vorhanden`);
    }

    function renderRain(scenario) {
        document.getElementById('rain-product-time').textContent = scenario === 'empty' ? 'Produktzeit —' : 'Produktzeit 12:00';
        document.getElementById('rain-headline').textContent = scenario === 'empty' ? 'Keine Vorhersage vorhanden' : scenario === 'stale' ? 'Letzte Vorhersage · veraltet' : 'Regen ab +15 min';
        document.getElementById('rain-chart').hidden = scenario === 'empty';
        document.getElementById('rain-detail-field').hidden = scenario === 'empty';
        document.getElementById('rain-detail').hidden = scenario === 'empty';
        notice('rain-notice', scenario === 'stale' ? '? Die dargestellte Vorhersage ist veraltet.' : scenario === 'normal' ? '? Minute +36 fehlt · schraffiert dargestellt.' : 'Keine Daten · daraus lässt sich kein trockenes Zeitfenster ableiten.');
        document.getElementById('rain-stamp').textContent = scenario === 'stale' ? 'Beispieldaten · letzter Stand vor 2 Stunden · letzte Produktzeit 12:00' : scenario === 'empty' ? 'Beispieldaten · kein Produktstand vorhanden' : 'Beispieldaten · Ein Segment je Minute · Intensität in mm/h';
        updateMinute();
    }

    minutes.forEach((entry, minute) => {
        const bar = element('span', 'example-rain-bar');
        if (entry) bar.style.background = entry.color;
        else bar.setAttribute('data-missing', '');
        document.getElementById('rain-bars').append(bar);
        const option = element('option', '', `+${minute} min`);
        option.value = String(minute);
        minuteSelect.append(option);
    });
    minuteSelect.value = '25';
    minuteSelect.addEventListener('change', updateMinute);
    document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
        filter = button.dataset.filter;
        document.querySelectorAll('[data-filter]').forEach(el => el.setAttribute('aria-pressed', String(el.dataset.filter === filter)));
        renderBattery(scenarioSelect.value);
    }));
    document.getElementById('preview-theme').addEventListener('change', event => { preview.dataset.theme = event.target.value; });
    document.getElementById('preview-width').addEventListener('change', event => { preview.dataset.width = event.target.value; });
    scenarioSelect.addEventListener('change', () => {
        lights = [true, false, null];
        document.getElementById('light-result').textContent = scenarioSelect.value === 'stale' ? 'Beispieldaten · letzter Stand vor 2 Tagen' : scenarioSelect.value === 'empty' ? 'Beispieldaten · kein Status vorhanden' : 'Beispieldaten · Stand vor 2 Minuten';
        render();
    });
    function render() {
        renderBattery(scenarioSelect.value);
        renderLights(scenarioSelect.value);
        renderRain(scenarioSelect.value);
    }
    render();
})();
