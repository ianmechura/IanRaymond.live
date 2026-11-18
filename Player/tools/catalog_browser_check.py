"""Exercise release grouping and the staged admin workflow in an isolated Chrome profile."""
from browser_check import Browser, ACTIVE, ROOT
from pathlib import Path
import json
b=Browser();b.size(1440,1000)
override="""
const original=v.tracks.map((t,i)=>({...t,releaseId:'root-pass',releaseTitle:'Root Pass',releaseArtwork:v.artwork,releaseNumber:i+1}));
v.releases=[{id:'root-pass',type:'album',title:'Root Pass',artwork:v.artwork},{id:'new-single',type:'single',title:'New Single',artwork:''}];
v.tracks=[...original,{...original[0],id:'new-single-song',title:'Single Song',artwork:'',releaseId:'new-single',releaseTitle:'New Single',releaseArtwork:'',releaseNumber:1}];
v.title='Music';Object.assign(v.secret.track,{releaseId:'root-pass',releaseTitle:'Root Pass',releaseArtwork:v.artwork,releaseNumber:12});
"""
b.load(override);b.wait("document.querySelectorAll('.release-section').length===2")
assert b.js("[...document.querySelectorAll('.release-header h3')].map(n=>n.textContent)")==['Root Pass','New Single']
assert b.js("document.querySelector('[data-release-id=\"new-single\"] .track-number').textContent")=='01'
b.click('[data-track-id="new-single-song"]');b.wait(ACTIVE+'.currentTime>.3 && !'+ACTIVE+'.paused')
assert b.js("document.querySelector('#nowArtist').textContent").endswith('New Single')
assert b.js("document.querySelector('#coverImage').hidden && document.querySelector('#coverTitle').textContent==='NEW\\nSINGLE'")
assert b.js('navigator.mediaSession.metadata.album')=='New Single'
b.click('[data-track-id="nyquil"]');b.wait(ACTIVE+'.currentTime>.2');b.js(ACTIVE+'.currentTime=10.2');b.wait("!document.querySelector('#secretBottle').hidden")
b.click('#secretBottle');b.click('#secretAdd');b.click('.track-row.secret');b.wait(ACTIVE+'.currentTime>.3')
assert b.js("document.querySelector('.track-row.secret').closest('.release-section').dataset.releaseId")=='root-pass'
b.click('#next');b.wait("document.querySelector('#nowTitle').textContent==='Single Song'");b.wait(ACTIVE+'.currentTime>.2')
b.click('[data-track-id="nyquil"]');b.wait(ACTIVE+'.currentTime>.2');b.click('#next');b.wait("document.querySelector('#nowTitle').textContent==='Naked Billy B-Side Remix'");b.wait(ACTIVE+'.currentTime>.2')
b.click('#play');b.size(390,844);assert b.js('document.documentElement.scrollWidth<=innerWidth')
b.screenshot('catalog-mobile.png');b.size(1440,1000);b.screenshot('catalog-desktop.png')
print('PASS: album and single sections, per-release numbering, fallback cover, media metadata, bonus grouping/playback and mobile layout.',flush=True)
# The admin uses the same code as WordPress. Only WP media and REST transport are fixtures.
fixture=ROOT/'review/catalog-admin-fixture.html';audio=ROOT/'review/catalog-upload-fixture.mp3'
fixture.write_text('''<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../web/admin.css"></head><body>
<p id="irl-catalog-status" role="status"></p><form id="irl-release-form"><fieldset id="irl-editor"><div id="irl-releases"></div><button type="button" id="irl-add-release">Add album or single</button><button type="submit">Save releases</button></fieldset></form>
<script>
window.IRL_ADMIN={api:'/fixture/',nonce:'test',media:'',player:''};window.saved=0;window.uploads=[];window.forceFail=false;
window.catalog={releases:[{id:'root-pass',title:'Root Pass',type:'album',artwork:'',artworkId:0,tracks:[{id:'a',title:'First Song',file:'media/a.mp3'},{id:'b',title:'Second Song',file:'media/b.mp3'}]}]};
window.fetch=async (url,options={})=>{
 if(window.forceFail)return {ok:false,json:async()=>({message:'Save failed for test'})};
 if(url.endsWith('catalog-upload')){const body=options.body;uploads.push({token:body.get('token'),index:body.get('index')});return {ok:true,json:async()=>Number(body.get('index'))===Number(body.get('total'))-1?{file:'media/'+body.get('token')+'-same-name.mp3',title:'Uploaded Song'}:{received:Number(body.get('index'))+1}};}
 if(options.method==='POST'){catalog=JSON.parse(options.body);saved++;}
 return {ok:true,json:async()=>({catalog:JSON.parse(JSON.stringify(catalog)),revision:'r'+saved})};
};
window.wp={media(){let callback;return {on(name,cb){callback=cb},state(){return {get(){return {first(){return {toJSON(){return {id:42,url:'https://example.test/cover.jpg'}}}}}}}},open(){callback()}}}};
</script><script src="../web/admin.js"></script></body></html>''')
audio.write_bytes(b'ID3'+b'a'*(1024*1024+50))
try:
 b.call('Page.navigate',{'url':fixture.as_uri()});b.wait("document.querySelectorAll('.irl-release').length===1 && !document.querySelector('#irl-editor').disabled")
 b.click('#irl-add-release');b.js("let n=document.querySelectorAll('.irl-release input[type=text]');n[n.length-1].value='My Single';n[n.length-1].dispatchEvent(new Event('input'))")
 b.js("let s=document.querySelectorAll('.irl-release select');s[s.length-1].value='single';s[s.length-1].dispatchEvent(new Event('change'))")
 b.js("[...document.querySelectorAll('.irl-release:last-child button')].find(n=>n.textContent==='Choose artwork').click()")
 doc=b.call('DOM.getDocument')['root']['nodeId'];input_id=b.call('DOM.querySelector',{'nodeId':doc,'selector':'.irl-release:last-child input[type=file]'})['nodeId']
 b.call('DOM.setFileInputFiles',{'nodeId':input_id,'files':[str(audio)]});b.wait("document.querySelectorAll('.irl-release:last-child .irl-song').length===1 && !document.querySelector('#irl-editor').disabled")
 assert b.js('saved')==0 and b.js('uploads.length')==2
 assert b.js("document.querySelector('.irl-release:last-child select').value")=='single'
 b.js("[...document.querySelectorAll('.irl-release:last-child .irl-release-controls button')].find(n=>n.textContent==='Move up').click()")
 b.click('button[type=submit]');b.wait('saved===1');assert b.js("catalog.releases[0].title==='My Single' && catalog.releases[0].artworkId===42 && catalog.releases[0].tracks.length===1")
 b.js("forceFail=true;{let n=document.querySelector('.irl-release input[type=text]');n.value='Retained Edit';n.dispatchEvent(new Event('input'));}")
 b.click('button[type=submit]');b.wait("document.querySelector('#irl-catalog-status').textContent.includes('Save failed')")
 assert b.js("document.querySelector('.irl-release input[type=text]').value")=='Retained Edit'
 # Clear unsaved-navigation handler only in the fixture before leaving it.
 errors=[e for e in b.events if e.get('method')=='Runtime.exceptionThrown'];assert not errors,errors
 print('PASS: admin load, add single, artwork selection, two-chunk upload, staged publication, ordering, save, and preserved edits after failure.',flush=True)
finally:
 fixture.unlink(missing_ok=True);audio.unlink(missing_ok=True);b.ws.close()
