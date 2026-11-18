"""Validate local content, optionally refresh the M3U or build a standalone USB ZIP."""
from pathlib import Path
import argparse, hashlib, json, os, re, zipfile
ROOT=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--write-playlist',action='store_true')
parser.add_argument('--package',action='store_true')
parser.add_argument('--verify-v6',action='store_true',help='Verify untouched original v6 MP3 identities; omit after intentional audio updates.')
args=parser.parse_args()
raw=(ROOT/'content/album.js').read_text()
match=re.search(r'window\.IAN_ALBUM\s*=\s*(\{.*\})\s*;',raw,re.S)
assert match,'Missing window.IAN_ALBUM data assignment'
data=json.loads(match.group(1))
assert data.get('schemaVersion')==1,'Unsupported schema'
assert data.get('tracks'),'No tracks'
issues=[]
def local(value,label):
 if not value:return
 if not isinstance(value,str) or re.match(r'^(?:[a-z][a-z0-9+.-]*:|[\\/])',value,re.I):
  issues.append(label+': must be a relative local path');return
 path=Path(os.path.normpath(ROOT/value))   # music/ holds symlinks to Albums/Root Pass/<song>/<song>.mp3
 if not path.is_relative_to(ROOT) or not path.is_file(): issues.append(label+': missing or outside package: '+value)
ids=set()
for track in data['tracks']:
 assert track.get('id') and track['id'] not in ids,'Missing or duplicate track ID'
 ids.add(track['id']);assert track.get('title'),'Missing title'
 assert track.get('file'),'Missing audio path'
 local(track['file'],track['title']);local(track.get('artwork'),track['title']+' artwork')
local(data.get('artwork'),'Album artwork')
for group,fields in [('welcome',['video','poster']),('booklet',['file']),('epk',['file']),('website',['qr'])]:
 for field in fields:local(data.get(group,{}).get(field),group+'.'+field)
for section in data.get('booklet',{}).get('sections',[]):local(section.get('image'),'Booklet image')
for file in ['PlayIanRaymond.html','assets/player.js','assets/player.css','assets/Oswald.woff2','assets/IBMPlexMono-Regular.woff2','START HERE.txt','CONTENT GUIDE.md','README.md']:local(file,file)
if issues:raise SystemExit('\n'.join(issues))
playlist=ROOT/data.get('playlist','music/Root Pass.m3u')
assert playlist.resolve().is_relative_to(ROOT)
# M3U paths are relative to the playlist, unlike content-index paths.
import os
lines=['#EXTM3U']+[Path(os.path.relpath(ROOT/t['file'],playlist.parent)).as_posix() for t in data['tracks']]
if args.write_playlist:playlist.write_text('\n'.join(lines)+'\n')
assert playlist.is_file(),'Missing playlist'
actual=[l.strip() for l in playlist.read_text().splitlines() if l.strip() and not l.startswith('#')]
assert actual==lines[1:],'Playlist differs from track order; use --write-playlist'
if args.verify_v6:
 baseline=json.loads((ROOT/'review/source-audio-sha256.json').read_text())
 assert len(baseline)==8
 for file,digest in baseline.items():assert hashlib.sha256((ROOT/file).read_bytes()).hexdigest()==digest,file+' differs from v6'
print('Validated',len(data['tracks']),'tracks, content references, required assets and playlist.')
if args.package:
 output=ROOT/'release/Ian-Raymond-Root-Pass-Player-v1.zip';output.parent.mkdir(exist_ok=True)
 import tempfile, subprocess
 staging=tempfile.TemporaryDirectory(prefix='root-pass-usb-')
 package_root=Path(staging.name)
 subprocess.run([__import__('sys').executable,str(ROOT/'tools/build_usb.py'),str(package_root)],check=True)
 files=sorted(p for p in package_root.rglob('*') if p.is_file())
 hashes={p.relative_to(package_root).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in files}
 temp=output.with_suffix('.zip.partial')
 with zipfile.ZipFile(temp,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=3) as archive:
  for p in files:archive.write(p,p.relative_to(package_root).as_posix())
  archive.writestr('PACKAGE-SHA256.json',json.dumps(hashes,indent=2)+'\n')
 with zipfile.ZipFile(temp) as archive:
  assert archive.testzip() is None
  for name,digest in hashes.items():assert hashlib.sha256(archive.read(name)).hexdigest()==digest
 temp.replace(output)
 staging.cleanup()
 print('Created and verified',output,'('+str(output.stat().st_size)+' bytes)')
