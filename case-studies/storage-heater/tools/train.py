"""Train private ridge artifact. Input is the private hourly export, never bundled."""
import argparse
import json
from pathlib import Path
import hashlib
import numpy as np
import pandas as pd

p = argparse.ArgumentParser()
p.add_argument('--hourly', required=True, type=Path)
p.add_argument('--reduced-switches', required=True, type=Path)
p.add_argument('--enabled-switches', required=True, type=Path)
p.add_argument('--output', required=True, type=Path)
a = p.parse_args()
df = pd.read_csv(a.hourly)
df.index = pd.to_datetime(df.ts, unit='s', utc=True).dt.tz_convert('Europe/Berlin')
assert not df.ts.duplicated().any() and np.all(np.diff(df.ts) == 3600)
switches = {}
for name, path in [('reduced', a.reduced_switches), ('enabled', a.enabled_switches)]:
    obj = json.loads(path.read_text())
    switches[name] = sorted(obj['pre'] + obj['rows'])

def at(name, timestamp):
    rows = [v for v in switches[name] if v[0] <= timestamp]
    if not rows:
        raise ValueError('Missing switch seed')
    return rows[-1][1]

features = ['hdd_night', 'reduced', 'hdd_reduced', 'gallery', 'sofa', 'previous_charge', 'fan_hours', 'outside_previous']
rows = []
for date in pd.date_range(df.index[0].date(), df.index[-1].date()):
    ds = str(date.date())
    start = pd.Timestamp(ds + ' 22:00', tz='Europe/Berlin')
    cutoff = pd.Timestamp(ds + ' 21:00', tz='Europe/Berlin')
    issue = pd.Timestamp(ds + ' 21:45', tz='Europe/Berlin')
    end = pd.Timestamp(str((date + pd.Timedelta(days=1)).date()) + ' 07:00', tz='Europe/Berlin')
    previous_start = pd.Timestamp(str((date - pd.Timedelta(days=1)).date()) + ' 22:00', tz='Europe/Berlin')
    previous_end = pd.Timestamp(ds + ' 07:00', tz='Europe/Berlin')
    night = df[(df.index >= start) & (df.index < end)]
    history = df[(df.index >= cutoff - pd.Timedelta(hours=24)) & (df.index < cutoff)]
    previous = df[(df.index >= previous_start) & (df.index < previous_end)]
    if len(history) != 24 or len(night) != (end-start).total_seconds()/3600 or len(previous) != (previous_end-previous_start).total_seconds()/3600:
        continue
    if not at('enabled', issue.timestamp()):
        continue
    reduced = at('reduced', issue.timestamp())
    hdd = max(0, 15-night.outside.mean())
    rows.append(dict(date=ds, end=int(end.timestamp()), kwh=night.energy.sum(), hdd_night=hdd,
                     reduced=reduced, hdd_reduced=hdd*reduced, gallery=history.gallery.iloc[-1],
                     sofa=history.sofa.iloc[-1], previous_charge=previous.energy.sum(),
                     fan_hours=history.fan.sum(), outside_previous=history.outside.mean()))
data = pd.DataFrame(rows).dropna()
if len(data) < 60:
    raise ValueError('At least 60 complete enabled nights required')

def fit(frame):
    x = frame[features].to_numpy(float)
    y = frame.kwh.to_numpy(float)
    if not np.isfinite(x).all() or not np.isfinite(y).all() or (y < 0).any():
        raise ValueError('Invalid training measurements')
    mean = x.mean(0)
    scale = x.std(0)
    scale[scale < 1e-8] = 1
    matrix = np.c_[np.ones(len(x)), (x-mean)/scale]
    penalty = np.eye(matrix.shape[1])*10
    penalty[0, 0] = 0
    coef = np.linalg.solve(matrix.T@matrix+penalty, matrix.T@y)
    return dict(schema=1, features=features, cutoffHour=21, window='22-07', timezone='Europe/Berlin',
                trainedThrough=int(frame.end.max()), trainingNights=len(frame),
                mean=mean.tolist(), scale=scale.tolist(), intercept=float(coef[0]), coefficients=coef[1:].tolist(),
                ranges=np.stack([x.min(0), x.max(0)], axis=1).tolist(), regularization=10,
                weatherTraining='realized hourly mean; operational weather is a forecast')

checks = []
for _, row in data.iterrows():
    if not '2026-01-01' <= row.date <= '2026-04-30':
        continue
    train = data[data.date < row.date]
    if len(train) < 45:
        continue
    model = fit(train)
    vector = row[features].to_numpy(float)
    pred = max(0, model['intercept'] + ((vector-np.array(model['mean']))/np.array(model['scale'])) @ np.array(model['coefficients']))
    checks.append(dict(date=row.date, actual=float(row.kwh), prediction=float(pred), features=vector.tolist(), model=model))
model = fit(data)
model['sourceHashes'] = {name: hashlib.sha256(path.read_bytes()).hexdigest() for name, path in
                       [('hourly', a.hourly), ('reduced', a.reduced_switches), ('enabled', a.enabled_switches)]}
model['validation'] = {'n': len(checks), 'maeKwh': float(np.mean([abs(r['prediction']-r['actual']) for r in checks])),
                       'weather': 'realized, not historical forecasts', 'protocol': 'expanding earlier nights only; fixed ridge=10'}
a.output.parent.mkdir(parents=True, exist_ok=True)
a.output.write_text(json.dumps(model, indent=2))
a.output.with_suffix('.validation.json').write_text(json.dumps(checks, indent=2))
print(json.dumps({'trainingNights': len(data), 'validation': model['validation']}))
