#!/usr/bin/env python3
"""Build the cPanel installer/update ZIP. No configuration or operational data."""
import argparse,hashlib,json,pathlib,re,stat,zipfile
root=pathlib.Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser();p.add_argument('--output',type=pathlib.Path,default=root/'dist');args=p.parse_args()
version=re.search(r"APP_VERSION\s*=\s*'([^']+)'",(root/'version.php').read_text()).group(1)
schema=int(re.search(r'DB_SCHEMA_VERSION\s*=\s*(\d+)',(root/'version.php').read_text()).group(1))
allowed_roots={'admin','inc','assets','docs','tests','migrations'}
allowed_files={'index.php','install.php','version.php','manifest.php','sw.js','offline.html','README.md','LICENSE','NOTICE','CHANGELOG.md','SECURITY.md','CONTRIBUTING.md','CODE_OF_CONDUCT.md','.htaccess','.gitignore'}
sw=root/'sw.js'
sw.write_text(re.sub(r"const CACHE='mustr-safety-static-[^']+';","const CACHE='mustr-safety-static-"+version+"';",sw.read_text()))
files={}
for f in sorted(root.rglob('*')):
 if not f.is_file():continue
 rel=f.relative_to(root)
 if (len(rel.parts)==1 and rel.name in allowed_files) or rel.parts[0] in allowed_roots:
  if f.is_symlink():raise SystemExit('Symlinks may not be packaged')
  files[rel.as_posix()]=f.read_bytes()
manifest={'app_id':'mustrhq-kitchen-safety','version':version,'schema':schema,'min_php':'8.1.0','files':{name:hashlib.sha256(data).hexdigest() for name,data in files.items()},'remove':[]}
(root/'release.json').write_text(json.dumps(manifest,indent=2)+'\n')
args.output.mkdir(parents=True,exist_ok=True)
archive=args.output/f'mustrhq-kitchen-safety-{version}.zip'
def archive_entry(name, directory=False):
 # cPanel honours Unix ZIP metadata. ZipFile.writestr defaults to 0600,
 # which may let PHP run while Apache cannot read CSS, scripts or fonts.
 info=zipfile.ZipInfo(name, date_time=(1980,1,1,0,0,0))
 info.create_system=3
 mode=(stat.S_IFDIR|0o755) if directory else (stat.S_IFREG|0o644)
 info.external_attr=(mode<<16)|(0x10 if directory else 0)
 info.compress_type=zipfile.ZIP_STORED if directory else zipfile.ZIP_DEFLATED
 return info
prefix='mustrhq-kitchen-safety/'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
 directories={prefix}
 for name in files:
  parent=pathlib.PurePosixPath(name).parent
  while str(parent)!='.':
   directories.add(prefix+parent.as_posix()+'/');parent=parent.parent
 for directory in sorted(directories,key=lambda d:(d.count('/'),d)):
  z.writestr(archive_entry(directory,True),b'')
 for name,data in files.items():
  z.writestr(archive_entry(prefix+name),data,compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
 z.writestr(archive_entry(prefix+'release.json'),(root/'release.json').read_bytes(),compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
with zipfile.ZipFile(archive) as z:
 if z.testzip():raise SystemExit('ZIP integrity check failed')
checksum=hashlib.sha256(archive.read_bytes()).hexdigest()
(archive.parent/(archive.name+'.sha256')).write_text(checksum+'  '+archive.name+'\n')
print(json.dumps({'path':str(archive.resolve()),'version':version,'files':len(files),'sha256':checksum}))
