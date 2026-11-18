"""Exercise the real file:// player in an isolated, muted Chrome session through CDP."""
from pathlib import Path
import sys, json, time, urllib.request, base64
sys.path.insert(0,str(Path(__file__).parent/'vendor'))
import websocket
TRACKS=__import__('json').loads(__import__('re').search(r'window\.IAN_ALBUM\s*=\s*(\{.*\})\s*;',(__import__('pathlib').Path(__file__).resolve().parents[1]/'content/album.js').read_text(),__import__('re').S)[1])['tracks'].__len__()   # track count from content/album.js
ROOT=Path(__file__).resolve().parents[1]
class Browser:
 def __init__(self):
  port=(ROOT/'review/browser-profile/DevToolsActivePort').read_text().splitlines()[0]
  pages=json.load(urllib.request.urlopen(f'http://127.0.0.1:{port}/json/list'))
  page=next(p for p in pages if p['type']=='page')
  self.ws=websocket.create_connection(page['webSocketDebuggerUrl'],origin='http://localhost',timeout=15)
  self.seq=0;self.events=[]
  for domain in ['Page','Runtime','Network']:self.call(domain+'.enable')
  self.call('Network.setBlockedURLs',{'urls':['http://*','https://*']})
  self.call('Page.addScriptToEvaluateOnNewDocument',{'source':'window.__audios=[]; const OriginalAudio=window.Audio; window.Audio=function(...args){const a=new OriginalAudio(...args); window.__audios.push(a);return a;};'})
 def call(self,method,params=None):
  self.seq+=1;seq=self.seq;self.ws.send(json.dumps({'id':seq,'method':method,'params':params or {}}))
  while True:
   data=json.loads(self.ws.recv())
   if data.get('id')==seq:
    if 'error' in data:raise RuntimeError(data['error'])
    return data.get('result',{})
   self.events.append(data)
 def js(self,source):
  r=self.call('Runtime.evaluate',{'expression':source,'returnByValue':True,'awaitPromise':True,'userGesture':True})
  if 'exceptionDetails' in r:raise AssertionError(r['exceptionDetails'])
  return r['result'].get('value')
 def wait(self,condition,seconds=12):
  deadline=time.monotonic()+seconds
  while time.monotonic()<deadline:
   if self.js(condition):return
   time.sleep(.1)
  raise AssertionError('Timed out: '+condition)
 def click(self,selector):self.js(f'document.querySelector({json.dumps(selector)}).click()')
 def load(self,override='',path=None):
  sid=None
  if override:
   sid=self.call('Page.addScriptToEvaluateOnNewDocument',{'source':"let data; Object.defineProperty(window,'IAN_ALBUM',{get(){return data;},set(v){"+override+";data=v;}});"})['identifier']
  self.call('Page.navigate',{'url':(path or ROOT/'PlayIanRaymond.html').as_uri()})
  self.wait("document.readyState==='complete' && !!document.querySelector('#loadMessage') && document.querySelector('#loadMessage').textContent !== 'Loading your album…'")
  if sid:self.call('Page.removeScriptToEvaluateOnNewDocument',{'identifier':sid})
 def size(self,w,h):self.call('Emulation.setDeviceMetricsOverride',{'width':w,'height':h,'deviceScaleFactor':1,'mobile':False})
 def screenshot(self,name):
  self.wait("document.fonts.status==='loaded'")
  png=self.call('Page.captureScreenshot',{'format':'png','captureBeyondViewport':False})['data']
  (ROOT/'review'/name).write_bytes(base64.b64decode(png))
ACTIVE="window.__audios.find(a=>a.getAttribute('src'))"
def basic_check():
 b=Browser();b.size(1440,1100);b.load()
 b.wait("document.querySelectorAll('.track-row').length==="+str(TRACKS)+" && [...document.querySelectorAll('.track-duration')].every(n=>!n.textContent.includes('—'))")
 b.screenshot('desktop.png')
 assert b.js("document.querySelector('#welcome').hidden && document.querySelector('#songNotes').hidden")
 b.click('#playAlbum');b.wait("document.querySelector('#playState').textContent==='NOW PLAYING'")
 b.wait(ACTIVE+'.currentTime>1')
 b.click('#play');assert b.js(ACTIVE+'.paused')
 b.js("document.querySelector('#seek').value=35;document.querySelector('#seek').dispatchEvent(new Event('input'))")
 assert b.js(ACTIVE+'.currentTime>=34')
 b.click('#next');b.wait("document.querySelector('#nowTitle').textContent.includes('Angel')")
 b.click('#previous');b.wait("document.querySelector('#nowTitle').textContent==='Josephine'")
 b.js("document.querySelector('#volume').value=.4;document.querySelector('#volume').dispatchEvent(new Event('input'))")
 assert abs(b.js(ACTIVE+'.volume')-.4)<.001
 b.click('#mute');assert b.js(ACTIVE+'.muted')
 b.click('#mute');assert not b.js(ACTIVE+'.muted')
 b.click('#play');b.size(390,844);b.screenshot('mobile.png')
 assert b.js('document.documentElement.scrollWidth<=window.innerWidth')
 errors=[e for e in b.events if e.get('method')=='Runtime.exceptionThrown'];assert not errors,errors
 remote=[e['params']['request']['url'] for e in b.events if e.get('method')=='Network.requestWillBeSent' and e['params']['request']['url'].startswith(('http:','https:'))];assert not remote,remote
 print('PASS: real file:// load, all MP3 durations, muted audio progression, seek, next/previous, volume, mute, optional sections, offline requests, desktop/mobile layout.',flush=True)
 (ROOT/'review/browser-basic-check.json').write_text(json.dumps({'browser':b.call('Browser.getVersion'),'passed':True,'http_requests':remote,'javascript_exceptions':errors},indent=2)+'\n')
 b.ws.close()

if __name__=="__main__": basic_check()
