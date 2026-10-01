import importlib.util,json,pathlib,tempfile,shutil,sys
root=pathlib.Path(__file__).resolve().parents[3]
spec=importlib.util.spec_from_file_location('prepare',root/'case-studies/profile-monitor/tools/prepare-candidate.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
base=pathlib.Path(sys.argv[1]).resolve()
with tempfile.TemporaryDirectory(prefix='preparation-test-',dir=root/'private') as d:
 p=pathlib.Path(d)
 a=m.prepare(base,p/'a');b=m.prepare(base,p/'b');assert a==b
 def reject(src,target):
  try:m.prepare(src,target)
  except ValueError:return
  raise AssertionError('Unsafe preparation accepted')
 reject(base,p/'a')
 reject(base,root/'public-output-prohibited')
 shutil.copytree(base,p/'wrong');f=p/'wrong/ProfileMonitor/module.php';f.write_bytes(f.read_bytes()+b'\n')
 reject(p/'wrong',p/'bad-source')
 (p/'linked').symlink_to(base,target_is_directory=True);reject(p/'linked',p/'bad-link')
print('Preparation tests passed: deterministic hashes, no overwrite, private paths, source pin and symlink rejection')
