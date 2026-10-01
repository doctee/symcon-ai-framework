#!/usr/bin/env python3
'''Local-only patch preparation; upstream files and generated evidence stay in ignored private/.
No download, publication, installation or implicit source selection.
'''
import argparse
import hashlib
import json
from pathlib import Path

HERE = Path(__file__).resolve().parents[1]
ROOT = HERE.parents[1]
HASHES = {
    "15d24dc7be0992426b8cb35d0c1125fa78520ce0912fd7ff0156f41b843c0614",
    "a0b2608ff8b01f85cc5e9f2f821f7537ff029c06fa8203ef2d87022cfa430178",
}


def digest(data):
    return hashlib.sha256(data).hexdigest()


def private_path(value):
    path = Path(value).absolute()
    if path.is_symlink() or any(x.is_symlink() for x in path.parents):
        raise ValueError("Symbolic links are not allowed")
    path = path.resolve()
    if not path.is_relative_to((ROOT / 'private').resolve()):
        raise ValueError("Upstream and generated files must remain below this worktree's private/")
    return path


def prepare(source, target):
    source, target = private_path(source), private_path(target)
    if target.exists():
        raise ValueError("Target must not exist; retain previous candidates")
    required = ['library.json', 'ProfileMonitor/module.json', 'ProfileMonitor/form.json',
                'ProfileMonitor/locale.json', 'ProfileMonitor/module.php']
    originals = {}
    for rel in required:
        f = source / rel
        if any(x.is_symlink() for x in f.parents):
            raise ValueError('Linked source parent')
        if f.is_symlink() or not f.is_file() or f.stat().st_size > 100000:
            raise ValueError("Missing, linked or oversized source: " + rel)
        originals[rel] = f.read_bytes()
    module = originals['ProfileMonitor/module.php'].decode()
    if digest(originals['ProfileMonitor/module.php']) not in HASHES:
        raise ValueError("Unknown upstream module hash; review baseline first")
    library = json.loads(originals['library.json'])
    metadata = json.loads(originals['ProfileMonitor/module.json'])
    if library['id'] != '{F6666B38-34A7-7220-938C-33B095A4397B}' or metadata['id'] != '{D28C98B1-FB03-3D25-8816-A9E31C39A034}' or metadata['prefix'] != 'BW':
        raise ValueError("Unexpected module identity")
    def replace_once(old, new):
        nonlocal module
        if module.count(old) != 1:
            raise ValueError("Patch anchor drift")
        module = module.replace(old, new)
    replace_once('class ProfileMonitor extends IPSModule {',
        "require_once __DIR__ . '/Evaluator.php';\nrequire_once __DIR__ . '/MonitorRuntime.php';\n\n"
        'class ProfileMonitor extends IPSModule {\n\tuse \\SAEF\\ProfileMonitor\\MonitorRuntime;')
    # Rust/Ninja require an explicit profile/presentation argument.
    replace_once('$this->RegisterVariableString("LastUpdate",$this->Translate(\'Last Update\'));',
                 '$this->RegisterVariableString("LastUpdate",$this->Translate(\'Last Update\'), "");')
    replace_once('$this->RegisterVariableInteger("Devices_With_Empty_Battery",$this->Translate(\'Device with empty battery\'));',
                 '$this->RegisterVariableInteger("Devices_With_Empty_Battery",$this->Translate(\'Device with empty battery\'), "");')
    # Public module calls may not echo errors under Rust. Preserve configured sends.
    for variable in ['EmailVariable', 'WebfrontVariable']:
        replace_once('if ($' + variable + ' != "") {', 'if ($' + variable + ' > 0) {')
    for message in ['Email Instance is not configured', 'Webfront Instance is not configured']:
        replace_once("echo $this->Translate('" + message + "');",
                     "throw new RuntimeException($this->Translate('" + message + "'));")
    replace_once('//Properties', '''//Properties
        $this->RegisterPropertyBoolean('PresentationMonitoring', false);
        $this->RegisterPropertyBoolean('MonitorZigbeeBattery', false);
        $this->RegisterPropertyBoolean('MonitorBlinkBattery', false);
        $this->RegisterPropertyInteger('PresentationPercentThreshold', 10);
        $this->RegisterPropertyInteger('BlinkBatteryThreshold', 2);
        $this->RegisterPropertyString('PresentationRules', '[]');
        $this->RegisterPropertyBoolean('SkipNeverUpdated', true);''')
    # Evaluate completely before changing LastUpdate, alarm or output variables.
    replace_once('public function Check() {', '''public function Check() {
        $analysis = $this->CollectMonitorAnalysis();
        $warningSet = array_fill_keys($analysis['warnings'], true);
        foreach ($analysis['details'] as $detail) {
            if ($detail['status'] !== 'ok') {
                $this->SendDebug('Monitor data quality', json_encode($detail), 0);
            }
        }''')
    start = module.index('\t\t$VariableIDs = IPS_GetVariableList();')
    stop = module.index('\n\t\t\t\tif ($warning) {', start)
    module = module[:start] + '''        $checked_variable_json = '[';
        foreach ($analysis['checked'] as $VariableID) {
            if ($VariableID != $WarningVariableID) {
                $checked_variable_json .= $VariableID . ',';
                $warning = isset($warningSet[$VariableID]);
''' + module[stop:]
    # Existing JSON contracts are kept; unknowns are not represented as healthy HTML.
    anchor = '$this->ReadPropertyString("HTMLBoxNothingFound").\'</b></th></tr></table>\';'
    replace_once(anchor, '(count($analysis[\'unknown\']) > 0 ? \'Keine Batteriewarnung; \' . count($analysis[\'unknown\']) . \' Werte unbekannt/ungeprüft\' : $this->ReadPropertyString("HTMLBoxNothingFound")).\'</b></th></tr></table>\';')
    form = json.loads(originals['ProfileMonitor/form.json'])
    form['elements'].append({'type': 'ExpansionPanel', 'caption': 'Variablendarstellungen', 'items': [
        {'type':'CheckBox','name':'PresentationMonitoring','caption':'Zusätzliche Erkennung aktivieren (ab Symcon 8.1)'},
        {'type':'CheckBox','name':'MonitorZigbeeBattery','caption':'Zigbee2MQTT: Batteriestand'},
        {'type':'NumberSpinner','name':'PresentationPercentThreshold','caption':'Batteriewarnung bis einschließlich','suffix':'%','minimum':0,'maximum':100},
        {'type':'CheckBox','name':'MonitorBlinkBattery','caption':'Blink-Kameras: Batteriezustand'},
        {'type':'Select','name':'BlinkBatteryThreshold','caption':'Blink-Warnstufe','options':[{'caption':'Niedrig','value':1},{'caption':'Niedrig und Mittel','value':2}]},
        {'type':'CheckBox','name':'SkipNeverUpdated','caption':'Werte ohne erste Aktualisierung als unbekannt behandeln'},
        {'type':'ValidationTextBox','name':'PresentationRules','caption':'Erweiterte Zuordnungsregeln (JSON)'},
        {'type':'Label','caption':'Vorschau prüft gespeicherte Zusatzregeln auch bei ausgeschalteter zusätzlicher Erkennung. Keine Warnwerte oder Meldungen werden ausgelöst.'}
    ]})
    form['actions'].append({'type':'Button','caption':'Gespeicherte Regeln prüfen (nur lesen)', 'onClick':'echo BW_PreviewPresentations($id);'})
    # Do not change library version, prefix, GUIDs, notifications or existing objects.
    output = dict(originals)
    output['ProfileMonitor/module.php'] = module.encode()
    output['ProfileMonitor/form.json'] = (json.dumps(form, ensure_ascii=False, indent=4)+'\n').encode()
    for name in ['Evaluator.php', 'MonitorRuntime.php']:
        output['ProfileMonitor/'+name] = (HERE/'candidate'/name).read_bytes()
    for name in ['LICENSE', 'PROVENANCE.md', 'README.md']:
        output[name] = (HERE/'fork'/name).read_bytes()
    manifest = {'schemaVersion':1, 'status':'offline-candidate-not-approved-for-installation',
                'inputs':{k:digest(v) for k,v in originals.items()},
                'outputs':{k:digest(v) for k,v in output.items()},
                'license':'MIT; upstream consent in Issue #8 comment 5935066332; see LICENSE and PROVENANCE.md',
                'liveInstallation':False}
    target.mkdir(parents=True)
    for rel,data in output.items():
        f=target/rel; f.parent.mkdir(parents=True,exist_ok=True); f.write_bytes(data)
    (target/'candidate-manifest.local.json').write_text(json.dumps(manifest,indent=2)+'\n')
    return manifest


if __name__ == '__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source'); parser.add_argument('target')
    args=parser.parse_args()
    print(json.dumps(prepare(args.source,args.target),indent=2))
