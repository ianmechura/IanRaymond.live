"""Check the NyQuil secret-song flow with stand-in audio (content overridden only inside the test browser)."""
from browser_check import Browser, ACTIVE
import json
TRACKS=__import__('json').loads(__import__('re').search(r'window\.IAN_ALBUM\s*=\s*(\{.*\})\s*;',(__import__('pathlib').Path(__file__).resolve().parents[1]/'content/album.js').read_text(),__import__('re').S)[1])['tracks'].__len__()   # track count from content/album.js
# Real content: NyQuil is the last track; the secret song is content/extras/Naked Billy B-Side Remix.mp3.
OVERRIDE=''
b=Browser();b.size(1440,1000);b.load(OVERRIDE)
n=b.js("document.querySelectorAll('.track-row').length");assert n==TRACKS,n
assert b.js("document.querySelector('#secretBottle').hidden")
b.click('.track-row[data-track-id="nyquil"]');b.wait("document.querySelector('#playState').textContent==='NOW PLAYING'")
b.js(ACTIVE+".currentTime=8.5");b.wait(ACTIVE+".currentTime>8.6")
assert b.js("document.querySelector('#secretBottle').hidden"),'bottle shown before 10 s'
b.wait("!document.querySelector('#secretBottle').hidden",8)
assert b.js(ACTIVE+".currentTime>=10")
b.screenshot('secret-bottle.png')
b.click('#secretBottle');assert b.js("document.querySelector('#secretDialog').open")
txt=b.js("document.querySelector('#secretDialog').textContent");assert 'Shure SM58' in txt and 'Suno Studio' in txt and 'Add “Naked Billy B-Side Remix” to the playlist' in txt,txt
b.screenshot('secret-note.png')
b.click('#secretClose');assert not b.js("document.querySelector('#secretDialog').open")
assert not b.js("document.querySelector('#secretBottle').hidden"),'bottle should stay until added'
b.click('#secretBottle');b.click('#secretAdd')
assert b.js("document.querySelectorAll('.track-row').length")==TRACKS+1
assert b.js("document.querySelector('#trackList li:last-child .track-row').classList.contains('secret')")
assert b.js("document.querySelector('#secretBottle').hidden && !document.querySelector('#secretDialog').open")
assert 'added to the end' in b.js("document.querySelector('#playerStatus').textContent")
assert not b.js("document.documentElement.classList.contains('psy')")
b.wait("!document.querySelector('#trackList li:last-child .track-duration').textContent.includes('—')")
b.click('#trackList li:last-child .track-row');b.wait("document.querySelector('#nowTitle').textContent==='Naked Billy B-Side Remix' && document.querySelector('#playState').textContent==='NOW TRIPPING'")
PSY="document.documentElement.classList.contains('psy')"
assert b.js(PSY) and not b.js("document.querySelector('#psyLayer').hidden")
assert b.js("document.querySelectorAll('#artistName .psy-l').length>5 && document.querySelectorAll('.psy-sprite').length===22")
assert b.js("document.querySelector('#playState').textContent")=='NOW TRIPPING'
b.wait("document.querySelector('#artistName').textContent!=='IAN RAYMOND.'",5)          # words change on the beat
b.wait("(()=>{const c=document.querySelector('#psyFractal');return c.width>0 && c.getContext('2d').getImageData(c.width/2|0,c.height/3|0,1,1).data.some(v=>v>0)})()",5)
import time;time.sleep(1.2);b.screenshot('secret-trip-1.png');b.js("scrollTo(0,640)");time.sleep(.9);b.screenshot('secret-trip-2.png');b.js("scrollTo(0,0)")
b.click('#play');assert not b.js(PSY),'trip should end when paused'
assert b.js("document.querySelector('#artistName').textContent")=='IAN RAYMOND.' and b.js("document.querySelector('#nowTitle').textContent")=='Naked Billy B-Side Remix'
assert b.js("document.querySelector('#coverTitle').textContent").replace('\n','') in ('ROOTPASS',) and b.js("!document.querySelector('.psy-l')")
b.click('#play');b.wait(PSY)
b.click('#previous');b.click('#previous');b.wait("document.querySelector('#nowTitle').textContent==='NyQuil'")
assert not b.js(PSY)
b.click('#next');b.wait("document.querySelector('#nowTitle').textContent==='Naked Billy B-Side Remix'")
# reduced-motion users get calm negative colours instead of the animation
b.call('Emulation.setEmulatedMedia',{'features':[{'name':'prefers-reduced-motion','value':'reduce'}]})
b.load(OVERRIDE);b.click('.track-row[data-track-id="nyquil"]');b.wait("document.querySelector('#playState').textContent==='NOW PLAYING'")
b.js(ACTIVE+".currentTime=10.2");b.wait("!document.querySelector('#secretBottle').hidden",5);b.click('#secretBottle');b.click('#secretAdd')
b.click('#trackList li:last-child .track-row');b.wait("document.documentElement.classList.contains('negative')")
assert not b.js("document.documentElement.classList.contains('psy')") and b.js("document.querySelector('#psyLayer').hidden")
b.call('Emulation.setEmulatedMedia',{'features':[{'name':'prefers-reduced-motion','value':'no-preference'}]})
# unlock happens once per session; a reload starts over
b.load(OVERRIDE);assert b.js("document.querySelectorAll('.track-row').length")==TRACKS
# without a trigger track configured, nothing secret appears
b.load("v.secret.revealDuringTrack=''");assert b.js("document.querySelectorAll('.track-row').length")==TRACKS
b.click('.track-row[data-track-id="nyquil"]');b.wait(ACTIVE+".currentTime>0.2");b.js(ACTIVE+".currentTime=12");b.wait(ACTIVE+".currentTime>12.3")
assert b.js("document.querySelector('#secretBottle').hidden")
errs=[e for e in b.events if e.get('method')=='Runtime.exceptionThrown'];assert not errs,errs
print('secret flow: all checks passed')
