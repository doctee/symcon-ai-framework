#!/usr/bin/env python3
"""Requires the privately prepared upstream and candidate module; never contacts Symcon."""
import json
import subprocess
import sys
import tempfile
from pathlib import Path
HERE = Path(__file__).resolve().parent
baseline, candidate = map(Path, sys.argv[1:])

def run(module, mode):
    return json.loads(subprocess.check_output(['php', str(HERE/'module.php'), str(module), mode]))

assert run(candidate, 'rust') == {'strictRegistration':True, 'outputFreeErrors':True}
before = run(baseline, 'legacy')
after = run(candidate, 'legacy')
assert before == after, (before, after)
native = run(candidate, 'native')
assert json.loads(native['values']['Profile_Monitor_AllCheckedVariables']) == [11,12,13,14,15]
assert json.loads(native['values']['Profile_Monitor_RAW']) == [11,14,15]
assert native['values']['Devices_With_Empty_Battery'] == 3
assert native['values']['Warning'] is True
assert len(native['notifications']) == len(before['notifications'])
preview = run(candidate, 'preview')
assert preview['warnings'] == [11,14,15]
assert run(candidate, 'failure') == {'failureBeforeWrites':True}
trigger = run(candidate, 'trigger')
assert trigger['values']['RemoteTrigger'] is False
assert trigger['values']['Profile_Monitor_RAW'] == native['values']['Profile_Monitor_RAW']
assert run(candidate, 'inactive')['timer'][1] == 0
with tempfile.TemporaryDirectory(prefix='saef-profile-monitor-test-') as directory:
    path = Path(directory)/'fixture.json'
    profiles = [{'ProfileName':'~Battery','ProfileValue':True},
                {'ProfileName':'~Battery.Reversed','ProfileValue':False},
                {'ProfileName':'~Battery.100','ProfileValue':'10'},
                {'ProfileName':'Voltage','ProfileValue':'2,2'},
                {'ProfileName':'GenericText','ProfileValue':'m'}]
    for value in [0,1,9,10,11,100]:
        fixture = {'variables': {
            11:{'value':value,'profile':'~Battery.100','type':1},
            12:{'value':value <= 10,'profile':'~Battery','type':0},
            13:{'value':value <= 10,'profile':'~Battery.Reversed','type':0},
            14:{'value':value/10,'profile':'Voltage','type':2},
            15:{'value':'a' if value < 10 else 'z','profile':'GenericText','type':3}},
            'properties':{'Profiles2Monitor':json.dumps(profiles)}}
        path.write_text(json.dumps(fixture))
        def fixture_run(module):
            return json.loads(subprocess.check_output(['php',str(HERE/'module.php'),str(module),'legacy',str(path)]))
        assert fixture_run(baseline) == fixture_run(candidate), ('differential',value)
print('Full module integration: original/candidate legacy outputs and notifications identical; native, preview, failure, trigger, timer, idempotency passed')
