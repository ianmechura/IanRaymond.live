"""Build the website version of the player (ianraymond.live/listen) from the same source as the USB player.
Usage: python3 tools/build_web.py
Output in release/web/:
  ian-raymond-listen.zip  WordPress plugin: install once (Plugins → Add New → Upload), then activate. Reinstall only when player code changes.
  upload/                 album.json + the MP3s (links to the latest renders). Upload them at Settings → Root Pass Player;
                          after a re-render, upload just the changed MP3s (plus album.json if songs were added, removed or reordered).
Web differences: main player streams; individual song pages offer downloads (no playlist link), no listening reports, "Listen online" wording, media from wp-content/uploads."""
import json,re,shutil,zipfile,os,hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];OUT=ROOT/'release/web';PLUG=OUT/'ian-raymond-listen';UP=OUT/'upload'
for p in (PLUG,UP):shutil.rmtree(p,ignore_errors=True)
(PLUG/'player').mkdir(parents=True);UP.mkdir(parents=True)
def rep(t,a,b,count=1):
 assert t.count(a)==count,(a,t.count(a));return t.replace(a,b)
# ---- page template (PHP fills {{ASSETS}}, {{BUILD}}, {{ALBUM_JSON}}) ----
html=(ROOT/'PlayIanRaymond.html').read_text()
html=rep(html,'  <script src="assets/listening-stats.js" defer></script>\n','')
html=rep(html,'  <script src="assets/player.js" defer></script>','  <script>window.IAN_BUILD="{{BUILD}}";window.IAN_ALBUM={{ALBUM_JSON}};</script>\n  <script src="assets/player.js?v={{BUILD}}" defer></script>')
html=re.sub(r'(href|src)="assets/',r'\1="{{ASSETS}}',html)
html=html.replace('{{ASSETS}}player.css"', '{{ASSETS}}player.css?v={{BUILD}}"')
html=rep(html,'<a class="text-link" href="music/Root%20Pass.m3u" id="playlistLink" download>','<a class="text-link" href="#" id="playlistLink" hidden>')   # stream only; element kept for player.js
html=rep(html,'<title>Root Pass · Ian Raymond</title>','<title>{{PAGE_TITLE}}</title>')
html=rep(html,'THE USB SESSIONS','LISTEN ONLINE')
html=rep(html,'<p class="eyebrow">02 / INSIDE THE RECORD</p><h2 id="insideHeading">A little more to the story.</h2>','<p class="eyebrow">02 / PRESS</p><h2 id="insideHeading">Press kit &amp; booking</h2>')
html=rep(html,'<p class="record-note">Listen from the first song, or find one that speaks to you.</p>','<p class="record-note" hidden></p>')   # kept (empty) for the secret mode's text effects
html=rep(html,'YOUR COPY. READY TO PLAY.','READY TO PLAY.')
html=re.sub(r'<noscript>.*?</noscript>','<noscript><div class="noscript">This player needs JavaScript. Please enable it to listen.</div></noscript>',html,flags=re.S)
assert 'music/' not in html and 'drive.js' not in html,'USB-only reference left in web page'
(PLUG/'player/index.html').write_text(html)
# ---- homepage mode: the player replaces the theme's hero on ianraymond.live (scoped so the theme and player styles don't collide) ----
def scope_selector(s):
 s=s.strip();P='#ir-player'
 s=re.sub(r'(^|[\s>+~,(])main(?=[\s>+~.:#\[,)]|$)',r'\1.ir-main',s)          # the player's <main> becomes div.ir-main
 if s.startswith(':root'):return P+s[5:]
 m=re.match(r'html((?:[.:][\w-]+)*)(.*)$',s)
 if m:
  cls,rest=m.groups();rest=rest.strip()
  if rest.startswith('body'):
   rest=rest[4:]
   if cls:return 'html'+cls+' body'+rest                                    # psychedelic background: whole page, on purpose
   return P+rest
  return (('html'+cls+' ') if cls else '')+P+((' '+rest) if rest else '')
 m=re.match(r'body(.*)$',s)
 if m:return P+m[1]
 return P+' '+s
def scope_css(css):
 out=[];i=0
 while i<len(css):
  j=css.find('{',i)
  if j<0:break
  head=css[i:j].strip()
  if head.startswith('@media') or head.startswith('@supports'):
   depth=1;k=j+1
   while depth:depth+={'{':1,'}':-1}.get(css[k],0);k+=1
   out.append(head+'{'+scope_css(css[j+1:k-1])+'}');i=k;continue
  k=css.index('}',j)
  if head.startswith('@font-face') or head.startswith('@keyframes'):
   if head.startswith('@keyframes'):
    depth=1;k=j+1
    while depth:depth+={'{':1,'}':-1}.get(css[k],0);k+=1
    out.append(css[i:k]);i=k;continue
   out.append(css[i:k+1]);i=k+1;continue
  out.append(','.join(scope_selector(x) for x in head.split(','))+css[j:k+1]);i=k+1
 return ''.join(out)
css=(ROOT/'assets/player.css').read_text()
# The player's body padding (room for the fixed dock) belongs to the real page body, not #ir-player, where it left a gap above the theme's next section.
home_css=scope_css(css)+'\nbody{padding-bottom:160px}@media(max-width:680px){body{padding-bottom:206px}}#ir-player{padding-bottom:0!important}#ir-player .inside-section{padding-bottom:0}'\
 '#ir-player #connect,#ir-player .ir-main>.site-footer,#ir-player #listeningPrivacy{display:none!important}\n'
(PLUG/'player/assets').mkdir(exist_ok=True);(PLUG/'player/assets/player-home.css').write_text(home_css)
body=html[html.index('>',html.index('<body'))+1:html.index('</body>')]
body=re.sub(r'<header class="site-header[^"]*".*?</header>','<span id="insideNav" hidden></span><span id="connectNav" hidden></span>',body,count=1,flags=re.S)
body=rep(body,'<main id="top" class="wrap">','<div id="top" class="wrap ir-main">');body=rep(body,'</main>','</div>')
body=re.sub(r'<script>window\.IAN_BUILD.*?</script>','',body,flags=re.S)
home_js="<script>document.addEventListener('click',e=>{const play=document.getElementById('play');if(e.target.closest('.youtube-player button')&&play?.getAttribute('aria-label')==='Pause')play.click();},true);</script>"   # pause before the theme replaces the clicked video button; includes bonus playback and loading
(PLUG/'player/home-fragment.html').write_text('<div id="ir-player">'+body+'</div>'+home_js)
(PLUG/'player/home-head.html').write_text('<link rel="stylesheet" href="{{ASSETS}}player-home.css?v={{BUILD}}">\n<script>window.IAN_BUILD="{{BUILD}}";window.IAN_ALBUM={{ALBUM_JSON}};</script>\n<script src="{{ASSETS}}player.js?v={{BUILD}}" defer></script>\n')
# ---- album data: songs come from the uploads folder (media/…), artwork ships with the plugin (content/…) ----
raw=(ROOT/'content/album.js').read_text();data=json.loads(re.search(r'window\.IAN_ALBUM\s*=\s*(\{.*\})\s*;',raw,re.S)[1])
media=[]
def to_media(path):
 src=ROOT/path;name=src.name;media.append((name,src));return 'media/'+name
for t in data['tracks']:t['file']=to_media(t['file'])
if data.get('secret',{}).get('track',{}).get('file'):data['secret']['track']['file']=to_media(data['secret']['track']['file'])
data['playlist']=''                      # stream only
data.setdefault('epk',{})['file']=''     # the online press kit link is used instead
(PLUG/'player/album.json').write_text(json.dumps(data,indent=1,ensure_ascii=False))
(UP/'album.json').write_text(json.dumps(data,indent=1,ensure_ascii=False))
for name,src in media:   # link to the song's stable "latest" link, so the upload folder follows new renders
 target=Path(os.path.normpath(src.parent/os.readlink(src))) if src.is_symlink() else src
 (UP/name).symlink_to(os.path.relpath(target,UP))
 wav=target.with_suffix('.wav')
 if wav.exists(): (UP/Path(name).with_suffix('.wav')).symlink_to(os.path.relpath(wav,UP))
# ---- bundled files ----
for name in ['ian-raymond-listen.php','catalog.php','songs.php','epk.php','admin.js','admin.css']:
 shutil.copy2(ROOT/'web'/name,PLUG/name)
(PLUG/'player/assets').mkdir(exist_ok=True)
for f in (ROOT/'assets').iterdir():
 if f.name!='listening-stats.js':shutil.copy2(f,PLUG/'player/assets'/f.name)
sprites=re.search(r'const SPRITES = (\[.*?\]);',(ROOT/'assets/player.js').read_text(),re.S)[1]   # one source for the medicine art
trip=PLUG/'player/assets/song-trip.js';trip.write_text(rep(trip.read_text(),'/*SPRITES*/[]',sprites))
used={v for v in re.findall(r'"(content/[^"]+)"',json.dumps(data))}
for rel in used:
 d=PLUG/'player'/rel;d.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(ROOT/rel,d)
zp=OUT/'ian-raymond-listen.zip';zp.unlink(missing_ok=True)
with zipfile.ZipFile(zp,'w',zipfile.ZIP_DEFLATED) as z:
 for f in sorted(PLUG.rglob('*')):
  if f.is_file():z.write(f,f.relative_to(OUT).as_posix())
size=sum((UP/n).stat().st_size for n,_ in media)
print(f'Plugin: {zp.relative_to(ROOT)} ({zp.stat().st_size/1e6:.1f} MB)\nUpload: {UP.relative_to(ROOT)}/ album.json + {len(media)} MP3s ({size/1e6:.0f} MB), largest {max((UP/n).stat().st_size for n,_ in media)/1e6:.1f} MB')

print(f'WAV masters staged separately: {len(list(UP.glob("*.wav")))}')
