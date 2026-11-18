from browser_check import Browser, ROOT, ACTIVE
from pathlib import Path
import zipfile,json,shutil
archive=ROOT/'release/Ian-Raymond-Root-Pass-Player-v1.zip'
relocated=ROOT/'review/USB copy with spaces'
relocated.mkdir(exist_ok=True)
with zipfile.ZipFile(archive) as z:
 assert not any(n.startswith(('tools/','review/')) for n in z.namelist())
 z.extractall(relocated)
b=Browser();b.size(1366,900);b.load(path=relocated/'PlayIanRaymond.html')
b.wait("document.querySelectorAll('.track-row').length===8 && [...document.querySelectorAll('.track-duration')].every(n=>!n.textContent.includes('—'))")
b.click('#playAlbum');b.wait(ACTIVE+'.currentTime>0.5');b.click('#play')
# Confirm folder relocation actually changed the audio source rather than using the development files.
assert 'USB%20copy%20with%20spaces/music/' in b.js(ACTIVE+'.src')
fixture=ROOT/'review/missing-index/PlayIanRaymond.html';fixture.parent.mkdir(exist_ok=True)
fixture.write_text((ROOT/'PlayIanRaymond.html').read_text().replace('href="assets/','href="../../assets/').replace('src="assets/','src="../../assets/'))
b.load(path=fixture)
b.wait("document.querySelector('#loadMessage').textContent.includes('could not be loaded')")
assert b.js("document.querySelector('#play').disabled")
# Inspect the supplied QR, EPK buttons and lower-page layout in the real package.
b.load();b.wait("document.querySelectorAll('.track-row').length===8")
b.js("document.querySelector('#connect').scrollIntoView({behavior:'instant'})")
b.wait("document.querySelector('#websiteQR').complete && document.querySelector('#websiteQR').naturalWidth>0")
b.screenshot('content-and-links.png')
errors=[e for e in b.events if e.get('method')=='Runtime.exceptionThrown'];assert not errors,errors
(ROOT/'review/release-smoke-check.json').write_text(json.dumps({'relocated_package_playback':True,'path_with_spaces':True,'missing_content_index_fallback':True,'website_qr_loaded':True,'javascript_exceptions':errors},indent=2)+'\n')
# Close the isolated test browser before removing its disposable extracted package.
b.call('Browser.close');b.ws.close()
shutil.rmtree(relocated);shutil.rmtree(fixture.parent)
print('PASS: extracted USB ZIP plays from a relocated path with spaces; missing index degrades cleanly; QR loads. Test browser closed.')
