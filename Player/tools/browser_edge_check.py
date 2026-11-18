"""Integration cases against actual browser media elements and local content files."""
from browser_check import Browser, ROOT, ACTIVE
import json, wave, time
TRACKS=__import__('json').loads(__import__('re').search(r'window\.IAN_ALBUM\s*=\s*(\{.*\})\s*;',(__import__('pathlib').Path(__file__).resolve().parents[1]/'content/album.js').read_text(),__import__('re').S)[1])['tracks'].__len__()   # track count from content/album.js
b=Browser();b.size(1280,900);passed=[]
def ready(count=TRACKS):b.wait(f"document.querySelectorAll('.track-row').length==={count}")
def title():return b.js("document.querySelector('#nowTitle').textContent")
def end():b.js(ACTIVE+".dispatchEvent(new Event('ended'))")
def start():b.click('#playAlbum');b.wait("document.querySelector('#playState').textContent==='NOW PLAYING'")
def record(name):passed.append(name);print('PASS:',name,flush=True)
b.load();ready();start()
b.click('#shuffle');seen=[title()]
for _ in range(TRACKS-1):
 end();b.wait("document.querySelector('#nowTitle').textContent!=="+json.dumps(seen[-1]));seen.append(title())
assert len(set(seen))==TRACKS,seen
end();b.wait("document.querySelector('#playerStatus').textContent.includes('End of album')")
record('Shuffle visits every track once and stops with repeat off')
b.click('#shuffle');b.click('#repeat');b.click('.track-list li:last-child button');b.wait("document.querySelector('#nowTitle').textContent.includes('NyQuil')")
end();b.wait("document.querySelector('#nowTitle').textContent==='Josephine'")
record('Repeat album wraps from the last song to the first')
b.click('#repeat');before=title();end();assert title()==before
b.wait("document.querySelector('#playState').textContent==='NOW PLAYING'")
record('Repeat one keeps the same song')
for _ in range(TRACKS+1):b.click('#next')
b.wait("document.querySelector('#playState').textContent==='NOW PLAYING'")
record('Rapid track changes settle without uncaught errors')
# A missing middle song is excluded from automatic advance.
b.load("v.tracks=v.tracks.slice(0,3);v.tracks[1].file='music/not-present.mp3'");ready(3)
b.wait("document.querySelectorAll('.track-row.unavailable').length===1")
start();end();b.wait("document.querySelector('#nowTitle').textContent==='The Devil at the Door'")
assert 'skipped' in b.js("document.querySelector('#playerStatus').textContent")
record('Missing middle track is marked, skipped and explained')
b.load("v.tracks=v.tracks.slice(0,3);v.tracks.forEach((t,i)=>t.file='music/missing-'+i+'.mp3')");ready(3)
b.wait("document.querySelectorAll('.track-row.unavailable').length===3 && document.querySelector('#play').disabled")
assert 'No tracks can be played' in b.js("document.querySelector('#playerStatus').textContent")
count=b.js('window.__audios.length');time.sleep(.5);assert count==b.js('window.__audios.length')
record('All missing tracks stop cleanly without a skip loop')
# Empty content still leaves a useful, non-playing page.
b.load("v.tracks=[];v.epk={};v.website={};v.socialLinks=[];v.booking={}")
assert b.js("document.querySelector('#inside').hidden && document.querySelector('#connect').hidden && document.querySelector('#play').disabled")
record('Empty track/content lists degrade without errors')
# Full supplied content, safe text rendering, fallback artwork, booklet and video.
full="""
v.tracks[0].story='Fixture story <img src=x onerror=alert(1)>';v.tracks[0].lyrics='Fixture line one\\nFixture line two';v.tracks[0].credits='Fixture credits';
v.tracks[0].artwork='content/artwork/missing.jpg';v.artwork='content/artwork/website-qr.svg';
v.welcome={text:'Fixture welcome',video:'review/fixtures/intro.mp4'};
v.booklet={title:'Fixture booklet',intro:'Fixture introduction',sections:[{heading:'Fixture heading',text:'Fixture text',image:'content/artwork/website-qr.svg',caption:'Fixture caption'},{heading:'Empty omitted',text:''}]};
"""
b.load(full);ready();b.wait("!document.querySelector('#coverImage').hidden")
assert b.js("!document.querySelector('#songNotes').hidden && document.querySelectorAll('#noteContent details').length===3 && document.querySelectorAll('#noteContent img').length===0")
assert 'website-qr.svg' in b.js("document.querySelector('#coverImage').src")
record('Optional song text renders safely; missing song artwork falls back to album art')
b.js("[...document.querySelectorAll('#documentLinks button')].find(x=>x.textContent.includes('booklet')).click()")
assert b.js("document.querySelector('#documentDialog').open && document.querySelectorAll('.booklet-section').length===1")
b.click('#closeDocument')
b.js("[...document.querySelectorAll('#documentLinks button')].find(x=>x.textContent.includes('Press kit')).click()")
assert b.js("document.querySelector('#documentDialog').open && document.querySelector('#documentBody object').data.includes('Ian-Raymond-EPK.pdf')")
b.click('#closeDocument');record('Assembled booklet, offline EPK preview and accessible dialog close')
start();b.js("document.querySelector('#welcomeVideo').play()")
b.wait(ACTIVE+'.paused');b.click('#play');b.wait("document.querySelector('#welcomeVideo').paused");record('Music and welcome video pause each other')
b.load("v.artwork='content/artwork/missing.jpg';v.tracks[0].artwork='content/artwork/also-missing.jpg';v.welcome={video:'content/video/missing.mp4'};v.website.qrDestination='https://different.invalid/'");ready()
b.js("document.querySelector('#welcomeVideo').play().catch(()=>{})")
b.wait("document.querySelector('#videoError').hidden===false")
assert b.js("document.querySelector('#coverImage').hidden && !document.querySelector('#coverType').hidden && document.querySelector('#websiteQR').hidden")
record('Missing video and all artwork fall back; mismatched website QR hides')
# Replace a local audio file under an identical name, then reopen the same HTML.
def wav(seconds):
 with wave.open(str(ROOT/'review/fixtures/replacement.wav'),'wb') as f:
  f.setnchannels(1);f.setsampwidth(2);f.setframerate(44100);f.writeframes(b'\0\0'*int(44100*seconds))
override="v.tracks=[{id:'replacement-test',title:'Replacement test',file:'review/fixtures/replacement.wav'}]"
wav(1);b.load(override);ready(1);b.wait(ACTIVE+'.duration===1')
wav(2);b.load(override);ready(1);b.wait(ACTIVE+'.duration===2')
record('Same-name audio replacement and updated duration appear after reopening')
# Restore the real album; keyboard operation and narrow viewport.
b.load();ready();b.size(320,760)
assert b.js('document.documentElement.scrollWidth<=window.innerWidth')
b.js("document.querySelector('#play').focus()")
b.call('Input.dispatchKeyEvent',{'type':'keyDown','key':'Enter','code':'Enter','text':'\r','unmodifiedText':'\r','windowsVirtualKeyCode':13})
b.call('Input.dispatchKeyEvent',{'type':'keyUp','key':'Enter','code':'Enter','windowsVirtualKeyCode':13})
b.wait("document.querySelector('#playState').textContent==='NOW PLAYING'")
b.click('#play');record('Keyboard activates playback; no horizontal overflow at 320 px')
errors=[e for e in b.events if e.get('method')=='Runtime.exceptionThrown'];assert not errors,errors
remote=[e['params']['request']['url'] for e in b.events if e.get('method')=='Network.requestWillBeSent' and e['params']['request']['url'].startswith(('http:','https:'))];assert not remote,remote
b.size(1440,1000);b.load();ready();b.wait("[...document.querySelectorAll('.track-duration')].every(n=>!n.textContent.includes('—'))")
b.screenshot('desktop.png');b.size(390,844);b.screenshot('mobile.png')
(ROOT/'review/browser-edge-check.json').write_text(json.dumps({'browser':b.call('Browser.getVersion'),'passed':passed,'uncaught_exceptions':errors,'http_requests':remote},indent=2)+'\n')
b.ws.close()
