"""Build the listener USB layout: PlayIanRaymond.html and Audio Files at the top, supporting files in hidden .assets.
Usage: python3 tools/build_usb.py /media/ian/<drive>
The folder is hidden twice: its name starts with a dot (macOS/Linux) and it gets the FAT 'hidden' attribute (Windows)."""
import sys,re,shutil,fcntl,struct
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];DEST=Path(sys.argv[1]);HID='.assets';H=DEST/HID
assert DEST.is_dir(),DEST
# Refresh only player-owned supporting files; preserve unrelated drive contents.
for p in [DEST/'PlayIanRaymond.html',H,DEST/'.rootpass']:
 if p.is_dir():shutil.rmtree(p)
 elif p.exists():p.unlink()
H.mkdir()
# Windows shows this as the drive name (FAT32 names are limited to 11 characters; set that with UDisks: "IAN RAYMOND").
# .hidden lists it for Linux file managers; the FAT hidden+system flags hide it on Windows and macOS.
(DEST/'autorun.inf').unlink(missing_ok=True)
(DEST/'autorun.inf').write_bytes(b'[autorun]\r\nlabel=Ian Raymond - Root Pass (Deluxe Edition)\r\n');(DEST/'.hidden').write_text('autorun.inf\n')
for folder in ['assets','content']:shutil.copytree(ROOT/folder,H/folder)
AUDIO=DEST/'Audio Files';AUDIO.mkdir(exist_ok=True)
shutil.copytree(ROOT/'music',AUDIO,dirs_exist_ok=True)
# Make the bonus recording directly available to DJs too.
bonus='Naked Billy B-Side Remix.mp3'
shutil.copy2(ROOT/'content/extras'/bonus,AUDIO/bonus)
(H/'content/extras'/bonus).unlink()
for f in ['drive.js','START HERE.txt','README.md','CONTENT GUIDE.md']:shutil.copy2(ROOT/f,H/f)
def rep(path,a,b,count=None):
 t=path.read_text();n=t.count(a);assert n and (count is None or n==count),(path,a,n);path.write_text(t.replace(a,b))
# Adapt listener instructions to the deployed audio folder.
start=H/'START HERE.txt'
start.write_text(start.read_text().replace('music/', 'Audio Files/').replace('music folder','Audio Files folder'))
# page references -> hidden folder
html=(ROOT/'PlayIanRaymond.html').read_text()
html=html.replace('music/','Audio%20Files/')
html=re.sub(r'(href|src)="(assets/|music/|LISTENING PRIVACY\.txt)',lambda m:f'{m[1]}="{HID}/{m[2]}',html)
(DEST/'PlayIanRaymond.html').write_text(html)
rep(H/'assets/player.js',"localURL('content/album.js')",f"localURL('{HID}/content/album.js')",1)
rep(H/'assets/listening-stats.js',"new URL('drive.js?v='",f"new URL('{HID}/drive.js?v='",1)
# content-index paths are relative to the page
a=H/'content/album.js';t=a.read_text()
t=t.replace('music/','Audio Files/').replace('content/extras/'+bonus,'Audio Files/'+bonus)
t=re.sub(r'"(content/[^"]*)"',lambda m:f'"{HID}/{m[1]}"',t);a.write_text(t)
# Windows hidden attribute (FAT_IOCTL_GET/SET_ATTRIBUTES; ATTR_HIDDEN=2) on vfat drives
try:
 import os
 for path,flags in [(H,2),(DEST/'autorun.inf',2|4|1),(DEST/'.hidden',2)]:   # hidden; autorun.inf also system + read-only
  fd=os.open(path,os.O_RDONLY)
  try:cur=struct.unpack('I',fcntl.ioctl(fd,0x80047210,b'\0'*4))[0];fcntl.ioctl(fd,0x40047211,struct.pack('I',cur|flags))
  finally:os.close(fd)
 print('FAT hidden attribute set on',HID,'autorun.inf .hidden')
except OSError as e:print('Not a FAT drive, hidden by dot-name only:',e)
n=sum(1 for p in H.rglob('*') if p.is_file());top=sorted(p.name for p in DEST.iterdir());print('Copied',n,'files; top level:',top)
extra=set(top)-{'PlayIanRaymond.html','Audio Files',HID,'autorun.inf','.hidden'}
if extra:print('WARNING: other items at the top of the drive:',sorted(extra))
