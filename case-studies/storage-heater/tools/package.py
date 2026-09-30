"""ZIP the exact SAEF publisher candidate; no separate packaging contract."""
from pathlib import Path
import argparse
import hashlib
import json
import subprocess
import tempfile
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True, type=Path)
args = parser.parse_args()
repo = Path(__file__).resolve().parents[3]
contract_path = "deployments/symcon/storage-heater-publication.json"
contract = json.loads((repo / contract_path).read_text())
args.output = args.output.resolve()
args.output.parent.mkdir(parents=True, exist_ok=True)

subprocess.run([
    "php", "tools/build-symcon-module-fileset.php",
    contract["generated"]["manifest"], "--check",
], cwd=repo, check=True)

# Disposable package staging only; the final ZIP is retained at the requested path.
with tempfile.TemporaryDirectory(prefix="storage-heater-package-", dir=args.output.parent) as staging:
    candidate = Path(staging) / "candidate"
    result = subprocess.run([
        "php", "tools/publish-symcon-module.php",
        "--contract=" + contract_path, "--prepare=" + str(candidate),
    ], cwd=repo, check=True, capture_output=True, text=True)
    print(result.stdout.strip())
    with zipfile.ZipFile(args.output, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for name in contract["inventory"]:
            info = zipfile.ZipInfo(name, date_time=(2026, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, (candidate / name).read_bytes())
print(hashlib.sha256(args.output.read_bytes()).hexdigest(), args.output)
