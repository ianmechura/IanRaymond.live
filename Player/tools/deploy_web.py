"""Deploy the bundled Player ZIP through SSH and remote WP-CLI; preview by default."""
import argparse
from datetime import datetime, timezone
from pathlib import Path
import re
import shlex
import subprocess
import uuid
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]


def plan(host, wp_path, archive, version, tag):
    remote_zip = '/tmp/ian-player-' + tag + '.zip'
    wp = 'wp --path=' + shlex.quote(wp_path)
    script = f'''set -eu
archive={shlex.quote(remote_zip)}
trap 'rm -f "$archive"' EXIT
command -v wp >/dev/null
{wp} core is-installed
backup="$HOME/.local/state/ian-player-backups/{tag}"
mkdir -p "$backup"
if {wp} plugin is-installed ian-raymond-listen; then
    installed=$({wp} plugin path ian-raymond-listen --dir)
    tar -czf "$backup/ian-raymond-listen.tar.gz" -C "$(dirname "$installed")" "$(basename "$installed")"
    printf 'Previous Player files backed up in %s\\n' "$backup"
fi
{wp} plugin install "$archive" --force --activate
actual=$({wp} plugin get ian-raymond-listen --field=version)
test "$actual" = {shlex.quote(version)}
{wp} plugin is-active ian-raymond-listen
{wp} rewrite flush
printf 'Player %s installed and active. Check /listen/ and a song page; purge any hosting/CDN page cache.\\n' "$actual"
'''
    return ['scp', str(archive), host + ':' + remote_zip], ['ssh', host, 'sh -s'], script


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--host', required=True, help='SSH config alias or user@hostname (use SSH config for ports/keys)')
    parser.add_argument('--wp-path', required=True, help='Absolute WordPress directory on the server')
    parser.add_argument('--zip', type=Path, default=ROOT / 'release/web/ian-raymond-listen.zip')
    parser.add_argument('--deploy', action='store_true', help='Actually upload and install; without this, only print the plan')
    args = parser.parse_args()
    if not re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.@-]*', args.host):
        parser.error('Use an SSH config alias or user@hostname without shell characters.')
    if not args.wp_path.startswith('/') or '\n' in args.wp_path:
        parser.error('--wp-path must be an absolute server path.')
    archive = args.zip.resolve()
    with ZipFile(archive) as package:
        source = package.read('ian-raymond-listen/ian-raymond-listen.php').decode()
        version = re.search(r'\* Version: ([0-9.]+)', source).group(1)
        if b"do_action( 'irl_catalog_saved'" not in package.read('ian-raymond-listen/catalog.php'):
            parser.error('This ZIP is missing the notification hook; rebuild it first.')
    tag = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '-' + uuid.uuid4().hex[:8]
    upload, connect, script = plan(args.host, args.wp_path, archive, version, tag)
    print(f'Player {version}: {archive}', flush=True)
    if not args.deploy:
        print(shlex.join(upload))
        print(shlex.join(connect) + ' <<\'REMOTE\'\n' + script + 'REMOTE')
        print('Preview only. Add --deploy to upload and install this ZIP.')
        return
    subprocess.run(upload, check=True)
    subprocess.run(connect, input=script, text=True, check=True)


if __name__ == '__main__':
    main()
