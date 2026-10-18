"""Fit a private prospective comparison artifact from prequalified nightly rows.

No archive access, live writes, future weather reconstruction, automatic activation,
or claim that training performance is a pre-issued forecast validation.
"""
import argparse
import hashlib
import json
from pathlib import Path
import numpy as np
import pandas as pd

p = argparse.ArgumentParser()
p.add_argument("--nights", type=Path, required=True)
p.add_argument("--output", type=Path, required=True)
a = p.parse_args()
df = pd.read_csv(a.nights).sort_values("date")
base = ["hdd_night", "reduced", "hdd_reduced", "gallery", "sofa",
        "previous_charge", "fan_hours", "outside_previous"]
assert len(df) >= 60 and not df.date.duplicated().any()
assert np.isfinite(df[base + ["night_fan_hours", "kwh"]].to_numpy(float)).all()
assert df.kwh.ge(0).all() and df.night_fan_hours.between(0, 10).all()
assert df.reduced.isin([0, 1]).all()
assert np.allclose(df.hdd_reduced, df.hdd_night * df.reduced)
end = pd.Timestamp(str((pd.Timestamp(df.date.iloc[-1]) + pd.Timedelta(days=1)).date()))
trained = int(end.tz_localize("Europe/Berlin").replace(hour=7).timestamp())

def fit(features):
    x = df[features].to_numpy(float)
    y = df.kwh.to_numpy(float)
    mean, scale = x.mean(0), x.std(0)
    scale[scale < 1e-8] = 1
    matrix = np.c_[np.ones(len(x)), (x - mean) / scale]
    penalty = np.eye(matrix.shape[1]) * 10
    penalty[0, 0] = 0
    b = np.linalg.solve(matrix.T @ matrix + penalty, matrix.T @ y)
    return dict(schema=1, features=features, cutoffHour=21, window="22-07",
                timezone="Europe/Berlin", trainedThrough=trained,
                trainingNights=len(df), mean=mean.tolist(), scale=scale.tolist(),
                coefficients=b[1:].tolist(), intercept=float(b[0]),
                ranges=np.c_[x.min(0), x.max(0)].tolist(), regularization=10,
                weatherTraining="realized; future operation uses frozen forecasts")

models = dict(schema=1, reference=fit(base), candidate=fit(base + ["night_fan_hours"]),
              provenance=dict(sourceSha256=hashlib.sha256(a.nights.read_bytes()).hexdigest(),
                              prospectiveValidation="pending",
                              windowHeatLossEnabled=False))
assert 0 <= models["candidate"]["coefficients"][-1] / models["candidate"]["scale"][-1] <= 10
a.output.parent.mkdir(parents=True, exist_ok=True)
a.output.write_text(json.dumps(models, indent=2) + "\n")
print(json.dumps(dict(nights=len(df), trainedThrough=trained,
                     fanCoefficientKwhPerHour=models["candidate"]["coefficients"][-1] /
                     models["candidate"]["scale"][-1])))

