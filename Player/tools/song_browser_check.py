"""Verify web song links and unchanged playback using a local HTTP fixture."""
from browser_check import Browser, ROOT, ACTIVE
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler
from functools import partial
from threading import Thread

class QuietHandler(SimpleHTTPRequestHandler):
 def log_message(self, *args): pass
 def copyfile(self, source, outputfile):
  try: super().copyfile(source, outputfile)
  except (BrokenPipeError, ConnectionResetError): pass

server = ThreadingHTTPServer(('127.0.0.1', 0), partial(QuietHandler, directory=str(ROOT)))
Thread(target=server.serve_forever, daemon=True).start()
b = Browser()
try:
 b.call('Network.setBlockedURLs', {'urls': []})
 origin = f'http://127.0.0.1:{server.server_port}'
 b.call('Page.addScriptToEvaluateOnNewDocument', {'source': "let album;Object.defineProperty(window,'IAN_ALBUM',{get(){return album},set(v){v.tracks.forEach(t=>t.pageUrl=location.origin+'/IanRaymond/Music/RootPass/'+t.title.replace(/[^a-z0-9]/ig,'')+'/');album=v;}});"})
 b.call('Page.navigate', {'url': origin + '/PlayIanRaymond.html'})
 for source in ['assets/song.js', 'web/admin.js']:
  b.js('new Function(' + __import__('json').dumps((ROOT/source).read_text()) + ')')
 b.wait("document.querySelector('.track-title[href]') && document.querySelector('.track-duration').textContent!=='—:—'")
 assert b.js("document.querySelector('.track-title').getAttribute('href')")==origin+'/IanRaymond/Music/RootPass/Josephine/'
 assert b.js("!document.querySelector('button a')")
 b.click('button.track-arrow'); b.wait(ACTIVE+'.currentTime>.2 && !'+ACTIVE+'.paused')
 b.js("window.linkClicked=false;document.querySelector('.track-title').addEventListener('click', e=>{e.preventDefault();window.linkClicked=true;});")
 b.click('.track-title')
 assert b.js('window.linkClicked && !'+ACTIVE+'.paused')
 b.click('button.track-arrow'); assert b.js(ACTIVE+'.paused')
 b.click('#next');b.wait("document.querySelector('#nowTitle').textContent.includes('Angel')")
 b.size(390,844); assert b.js('document.documentElement.scrollWidth<=innerWidth')
 errors=[e for e in b.events if e.get('method')=='Runtime.exceptionThrown']; assert not errors,errors
 print('PASS: song URL, separate accessible links and controls, title click preserves playback, play/pause, next, mobile width, no JS exceptions.')
finally:
 b.ws.close();server.shutdown()
