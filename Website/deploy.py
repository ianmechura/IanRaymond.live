#!/usr/bin/env python3
"""Deploy the site from this repo to ianraymond.live through your logged-in Chrome.

    python3 Website/deploy.py            # show what will change, ask once, deploy, verify
    python3 Website/deploy.py --check    # only show what would change

What it deploys:
  - Theme text files (Website/theme/ian-raymond: PHP/CSS/JS/SVG/TXT) that differ from the live site,
    saved through Appearance → Theme File Editor. WordPress checks PHP edits and rolls back a breaking change.
    Images, fonts and PDFs are not deployed (upload those by hand if they ever change).
  - The Music Player plugin (Player/release/web/ian-raymond-listen.zip) when its version differs from the
    installed one, via Plugins → Upload Plugin → "Replace current with uploaded". Build it first with
    python3 Player/tools/build_web.py.

Chrome must have remote debugging on (chrome://inspect/#remote-debugging) and be logged in to wp-admin.
Chrome may ask you to allow the connection; the script waits up to 5 minutes. It works in its own tab and closes it.
"""
import argparse, difflib, json, re, sys, time, urllib.parse, zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'Player/tools/vendor'))
import websocket  # noqa: E402  (vendored websocket-client)

SITE = 'https://ianraymond.live'
THEME = 'ian-raymond'
THEME_DIR = ROOT / 'Website/theme' / THEME
PLUGIN_ZIP = ROOT / 'Player/release/web/ian-raymond-listen.zip'
PLUGIN_FILE = 'ian-raymond-listen/ian-raymond-listen.php'
TEXT = {'.php', '.css', '.js', '.svg', '.txt', '.json', '.html'}


class Chrome:
    """One DevTools connection and one tab for the whole run."""

    def __init__(self):
        port, path = (Path.home() / '.config/google-chrome/DevToolsActivePort').read_text().split()
        print('Connecting to Chrome (approve "Allow remote debugging" in Chrome if it asks)…')
        self.ws = websocket.create_connection(f'ws://127.0.0.1:{port}{path}', timeout=300, suppress_origin=True)
        self.ws.settimeout(120)
        self.n = 0
        self.target = self.call('Target.createTarget', {'url': 'about:blank', 'background': True})['targetId']
        self.session = self.call('Target.attachToTarget', {'targetId': self.target, 'flatten': True})['sessionId']
        self.call('Page.enable', session=True)

    def call(self, method, params=None, session=False):
        self.n += 1
        msg = {'id': self.n, 'method': method, 'params': params or {}}
        if session:
            msg['sessionId'] = self.session
        self.ws.send(json.dumps(msg))
        while True:
            r = json.loads(self.ws.recv())
            if r.get('id') == self.n:
                if 'error' in r:
                    raise RuntimeError(f'{method}: {r["error"]}')
                return r['result']

    def js(self, expr):
        r = self.call('Runtime.evaluate', {'expression': expr, 'returnByValue': True, 'awaitPromise': True}, session=True)
        if 'exceptionDetails' in r:
            raise RuntimeError('Page script failed: ' + json.dumps(r['exceptionDetails'])[:500])
        return r['result'].get('value')

    def wait_ready(self):
        time.sleep(0.5)
        for _ in range(240):
            if self.js('document.readyState') == 'complete':
                return
            time.sleep(0.25)
        raise RuntimeError('Page did not finish loading')

    def go(self, url):
        self.call('Page.navigate', {'url': url}, session=True)
        self.wait_ready()
        if '/wp-login.php' in self.js('location.href'):
            raise RuntimeError('Chrome is not logged in to wp-admin. Log in and run again.')

    def close(self):
        try:
            self.call('Target.closeTarget', {'targetId': self.target})
        finally:
            self.ws.close()


def editor_url(name):
    return f'{SITE}/wp-admin/theme-editor.php?theme={THEME}&file={urllib.parse.quote(name, safe="")}'


def live_theme(c):
    c.go(f'{SITE}/wp-admin/theme-editor.php?theme={THEME}')
    names = c.js("[...document.querySelectorAll('#templateside a')].map(a => new URL(a.href).searchParams.get('file'))")
    out = {}
    for name in names:
        c.go(editor_url(name))
        out[name] = c.js("document.getElementById('newcontent')?.value")
    return out


def theme_changes(live):
    changes, problems = [], []
    for path in sorted(THEME_DIR.rglob('*')):
        if not path.is_file() or path.suffix.lower() not in TEXT:
            continue
        name = path.relative_to(THEME_DIR).as_posix()
        local = path.read_bytes().decode('utf-8')
        if name not in live:
            problems.append(f'{name}: not editable on the site (new file?) — upload it by hand')
        elif live[name] is not None and live[name].replace('\r\n', '\n') != local.replace('\r\n', '\n'):
            changes.append((name, live[name], local))
    for name in live:
        if not (THEME_DIR / name).exists():
            problems.append(f'{name}: on the site but not in the repo (left untouched)')
    return changes, problems


def save_theme_file(c, name, content):
    c.go(editor_url(name))
    c.js("""(() => { const v = %s; const cm = document.querySelector('.CodeMirror')?.CodeMirror;
        if (cm) { cm.setValue(v); cm.save(); } document.getElementById('newcontent').value = v;
        document.getElementById('submit').click(); })()""" % json.dumps(content))
    notice = ''
    for _ in range(60):
        time.sleep(1)
        notice = c.js("[...document.querySelectorAll('#file-editor-warning ~ .notice, .wrap > .notice, form .notice')]"
                      ".map(n => n.innerText.trim()).filter(Boolean).join(' | ')") or ''
        if re.search(r'success|error|unable|problem|could not', notice, re.I):
            break
    c.go(editor_url(name))  # confirm what the server now has
    saved = c.js("document.getElementById('newcontent').value")
    if saved.replace('\r\n', '\n') != content.replace('\r\n', '\n'):
        raise RuntimeError(f'{name} was not saved. WordPress said: {notice or "nothing"}')
    return notice


def zip_version():
    with zipfile.ZipFile(PLUGIN_ZIP) as z:
        return re.search(r'\* Version:\s*(\S+)', z.read(PLUGIN_FILE).decode())[1]


def installed_plugin_version(c):
    c.go(f'{SITE}/wp-admin/plugins.php?plugin_status=all')
    return c.js("""(() => { const r = document.querySelector('tr[data-plugin="%s"]');
        return r ? (r.querySelector('.plugin-version-author-uri')?.textContent.match(/Version ([\\d.]+)/) || [])[1] || '?' : null; })()""" % PLUGIN_FILE)


def install_plugin(c):
    c.go(f'{SITE}/wp-admin/plugin-install.php?tab=upload')
    root = c.call('DOM.getDocument', session=True)['root']['nodeId']
    node = c.call('DOM.querySelector', {'nodeId': root, 'selector': '#pluginzip'}, session=True)['nodeId']
    if not node:
        raise RuntimeError('Upload form not found (does your account have permission to install plugins?)')
    c.call('DOM.setFileInputFiles', {'nodeId': node, 'files': [str(PLUGIN_ZIP)]}, session=True)
    c.js("document.getElementById('install-plugin-submit').click()")
    c.wait_ready()
    replace = c.js("(() => { const a = [...document.querySelectorAll('a.update-from-upload-overwrite, a')].find(a => /Replace current with uploaded/i.test(a.textContent)); if (a) { a.click(); return true; } return false; })()")
    if replace:
        c.wait_ready()
    text = c.js('document.querySelector(".wrap")?.innerText || document.body.innerText')
    if not re.search(r'Plugin updated successfully|Plugin installed successfully', text):
        raise RuntimeError('WordPress did not confirm the plugin install:\n' + text[:800])


def verify(c):
    checks = []
    c.go(f'{SITE}/?deploycheck={int(time.time())}')
    checks.append(('Homepage title', c.js('document.title')))
    press_link = 'document.querySelector(\'footer.site-footer [data-ir-content="footer.press_kit_cta"]\')?.href'
    if c.js(press_link) != f'{SITE}/epk/':
        raise RuntimeError('Homepage footer does not link to the current press kit')
    c.go(f'{SITE}/listen/')
    checks.append(('/listen/ title', c.js('document.title')))
    c.go(f'{SITE}/IanRaymond/Music/RootPass/Josephine/')
    checks.append(('Song page', 'site header + footer' if c.js("!!document.querySelector('header.site-header') && !!document.querySelector('footer.site-footer')") else 'NO theme header/footer'))
    if c.js(press_link) != f'{SITE}/epk/':
        raise RuntimeError('Song footer does not link to the current press kit')
    checks.append(('Press kit footer links', '/epk/ on homepage and song page'))
    for label, value in checks:
        print(f'  {label}: {value}')


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--check', action='store_true', help='show what would change and stop')
    args = ap.parse_args()
    if not PLUGIN_ZIP.exists():
        sys.exit(f'{PLUGIN_ZIP} is missing. Run: python3 Player/tools/build_web.py')
    c = Chrome()
    try:
        print('Reading the live theme…')
        changes, problems = theme_changes(live_theme(c))
        live_version, new_version = installed_plugin_version(c), zip_version()
        print(f'\nTheme ({THEME}): {len(changes) or "no"} file(s) to update')
        for name, old, new in changes:
            diff = list(difflib.unified_diff(old.replace('\r\n', '\n').splitlines(), new.splitlines(), f'live/{name}', f'repo/{name}', lineterm='', n=1))
            print('\n'.join(line[:200] for line in diff))
        for p in problems:
            print('  note:', p)
        plugin_needed = live_version != new_version
        print(f'\nMusic Player plugin: installed {live_version or "not installed"}, repo zip {new_version}'
              + ('' if plugin_needed else ' — already current'))
        if not changes and not plugin_needed:
            print('\nNothing to deploy.')
            return
        if args.check:
            return
        if input('\nDeploy these changes to the live site? [y/N] ').strip().lower() != 'y':
            print('Cancelled. Nothing was changed.')
            return
        for name, _, new in changes:
            print(f'Saving theme {name}…', end=' ', flush=True)
            save_theme_file(c, name, new)
            print('done')
        if plugin_needed:
            print(f'Installing Music Player {new_version}…', end=' ', flush=True)
            install_plugin(c)
            print('done' if installed_plugin_version(c) == new_version else 'WARNING: version did not change')
        print('\nLive check:')
        verify(c)
    finally:
        c.close()


if __name__ == '__main__':
    main()
