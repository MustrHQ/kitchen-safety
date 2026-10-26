#!/usr/bin/env python3
"""Regression test for cPanel-readable ZIP permissions and release contents."""
import hashlib,json,pathlib,stat,subprocess,sys,tempfile,zipfile
root=pathlib.Path(__file__).resolve().parents[1]
def verify_archive(archive):
 with zipfile.ZipFile(archive) as z:
  assert z.testzip() is None,'ZIP CRC failure'
  files={}
  for entry in z.infolist():
   mode=entry.external_attr>>16
   assert entry.create_system==3,'Missing Unix metadata: '+entry.filename
   expected=0o755 if entry.is_dir() else 0o644
   assert stat.S_IMODE(mode)==expected,(entry.filename,oct(mode),'expected',oct(expected))
   assert stat.S_IFMT(mode)==(stat.S_IFDIR if entry.is_dir() else stat.S_IFREG),'Incorrect file type'
   if not entry.is_dir():files[entry.filename]=z.read(entry)
  prefix='mustrhq-kitchen-safety/'
  for name in ['install.php','index.php','admin/index.php','assets/app.css','assets/frontend.css','assets/app.js','assets/fonts/archivo-var.woff2','assets/brand/kitchen-safety.svg']:
   assert prefix+name in files,'Missing public resource: '+name
  assert not any('/config.php' in name or '/private/' in name or name.endswith(('.sql','.log','.tmp')) for name in files),'Private data in release'
  manifest=json.loads(files.pop(prefix+'release.json'))
  assert len(files)==len(manifest['files'])
  for name,digest in manifest['files'].items():assert hashlib.sha256(files[prefix+name]).hexdigest()==digest,'Manifest mismatch: '+name
  return len(files)
with tempfile.TemporaryDirectory(prefix='ks-package-') as tmp:
 result=subprocess.run([sys.executable,str(root/'scripts/build-release.py'),'--output',tmp],capture_output=True,text=True,check=True)
 built=json.loads(result.stdout);count=verify_archive(built['path'])
 # Confirm the regression check actually catches the original 0600 defect.
 bad=pathlib.Path(tmp)/'owner-only.zip'
 with zipfile.ZipFile(bad,'w') as z:z.writestr('mustrhq-kitchen-safety/assets/app.css','body{}')
 try:verify_archive(bad)
 except AssertionError:pass
 else:raise AssertionError('Owner-only permission regression was not detected')
 print(f'Release package verified: {count} hashed files, files 0644, directories 0755; 0600 negative control rejected.')
