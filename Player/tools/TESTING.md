# Development checks

Run from the `Player/` folder:

```sh
python3 tools/validate_package.py
python3 tools/validate_package.py --verify-v6
python3 tools/validate_package.py --write-playlist --package
```

Only use `--verify-v6` before intentionally replacing the audio. Package creation needs Python 3 but no third-party packages.

Browser checks use an isolated Chrome profile and bundled development-only `websocket-client`. Start Chrome with the command below (adapt executable/path as needed), leaving REAPER and your normal browser session alone:

```sh
google-chrome --headless --no-sandbox --disable-gpu --disable-dev-shm-usage --mute-audio --disable-background-networking --disable-component-update --no-first-run --no-default-browser-check --remote-debugging-port=0 --remote-allow-origins=http://localhost --user-data-dir="$PWD/review/browser-profile" about:blank
```

Then run `python3 tools/browser_check.py`, `python3 tools/browser_edge_check.py` and `python3 tools/secret_check.py` in another terminal. The edge cases use `review/fixtures/intro.mp4` (a short silent test video) and generate a replacement WAV fixture. They override the content index only within the test browser, without editing the real album index or MP3 files. The scripts block HTTP/HTTPS requests and open the actual local HTML page. Chrome's `--no-sandbox` is for this isolated automated test environment; listeners simply open the HTML in their normal browser.

`vendor/` contains development helpers with their own metadata/licenses. They are not dependencies of the player and are excluded from the listening ZIP. Close the isolated Chrome process after testing. Its disposable `review/browser-profile` may then be removed.
