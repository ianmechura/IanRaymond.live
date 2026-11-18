/* Offline, dependency-free player. Content is loaded as a classic script for file:// support. */
(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const stamp = String(window.IAN_BUILD || Date.now());   // web build: fixed per release so browsers can cache songs
  const text = value => typeof value === 'string' ? value.trim() : '';
  const time = seconds => Number.isFinite(seconds) && seconds >= 0 ? `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}` : '—:—';
  const web = /^https?:$/.test(location.protocol);
  function localURL(path) {
    if (!text(path)) return '';
    // USB (file://): relative paths inside the player folder only. Web: any URL on this same site (WordPress media, plugin files).
    if (!web && /^(?:[a-z][a-z\d+.-]*:|[\\/])/i.test(path)) return '';
    const base = new URL('.', location.href), url = new URL(path, base);
    if (url.origin !== base.origin || (!web && !url.pathname.startsWith(base.pathname))) return '';
    url.searchParams.set('v', stamp);
    return url.href;
  }
  function webURL(value) {
    try { const url = new URL(value); return ['https:', 'http:'].includes(url.protocol) ? url.href : ''; } catch { return ''; }
  }
  function externalLink(label, url) {
    const link = document.createElement('a'); link.textContent = label; link.href = url;
    link.target = '_blank'; link.rel = 'noopener noreferrer'; return link;
  }
  function paragraph(value, className = 'note-text') {
    const p = document.createElement('p'); p.className = className; p.textContent = value; return p;
  }
  function fatal(message) {
    $('loadMessage').textContent = message + (web ? ' Please try again later.' : ' You can still open the MP3 files in the music folder.');
    $('nowTitle').textContent = 'Album unavailable'; $('nowArtist').textContent = '';
  }
  const begin = () => {
    try { start(window.IAN_ALBUM); } catch (error) { console.error(error); fatal('The album content index needs checking.'); }
  };
  if (window.IAN_ALBUM) Promise.resolve().then(begin);   // web build embeds the album data; start once setup below has run
  else {
    const script = document.createElement('script');
    script.src = localURL('content/album.js');
    script.onerror = () => fatal('The album content index could not be loaded.');
    script.onload = begin;
    document.head.append(script);
  }

  function start(config) {
    if (!config || config.schemaVersion !== 1 || !Array.isArray(config.tracks)) throw new Error('Unsupported content index');
    const tracks = config.tracks.filter(t => t && text(t.title) && localURL(t.file));
    const releases = Array.isArray(config.releases) ? config.releases : [];
    const releaseLists = new Map();
    if (releases.length) {
      $('trackList').setAttribute('aria-label', 'Albums and singles');
      const musicLink = document.querySelector('a[href="#album"]');
      if (musicLink) musicLink.textContent = 'Music';
      document.querySelector('.section-kicker span').textContent = '01 / THE MUSIC';
      $('playAlbum').lastChild.textContent = releases.length > 1 ? ' Play all' : ' Play release';
    }
    let reports;
    try { reports = window.createListeningReports?.(config); } catch { /* Optional reporting must not prevent playback. */ }
    const artist = text(config.artist) || 'Ian Raymond', title = text(config.title) || 'Root Pass';
    $('artistName').textContent = artist.toUpperCase() + '.';
    $('albumHeading').textContent = title; $('albumSubtitle').textContent = text(config.subtitle); $('albumSubtitle').hidden = !text(config.subtitle);
    $('coverTitle').textContent = title.toUpperCase().replace(/\s+/, '\n');
    $('coverType').setAttribute('aria-label', `${title}, ${artist}`);
    $('coverEdition').textContent = text(config.subtitle).toUpperCase();
    $('editionLabel').textContent = text(config.edition).toUpperCase();
    $('nowArtist').textContent = artist; $('copyright').textContent = `© ${config.year || new Date().getFullYear()} ${artist}`;
    document.title = text(config.pageTitle) || `${title} · ${artist}`;
    const playlist = localURL(config.playlist);
    $('playlistLink').hidden = !playlist; if (playlist) $('playlistLink').href = playlist;
    let current = 0, audio = null, intent = false, shuffle = false, repeat = 'off';
    let showingAlbumCover = true;
    let bag = [], history = [], volume = 1, muted = false, pendingTimer = null;
    const failed = new Set(), durations = new Map(), rows = [], probes = new Set();
    // Secret song: revealed a few seconds into the trigger track, added to this session's list on request.
    const secret = config.secret || {}, secretNote = secret.note || {};
    const secretTrack = secret.track && text(secret.track.title) && localURL(secret.track.file) ? secret.track : null;
    const secretTrigger = text(secret.revealDuringTrack), secretDelay = Number.isFinite(Number(secret.revealAtSeconds)) ? Number(secret.revealAtSeconds) : 10;
    let secretIndex = -1, secretShown = false, tripping = false, tripTimer = null, tripStep = 0;
    const tripSaved = new Map(), calm = matchMedia('(prefers-reduced-motion: reduce)');
    const tripWords = {
      artistName: ['IAN RAYMOND.', 'NAKED BILLY.', 'NYQUIL DREAMS.', 'B-SIDE REMIX.', 'WHO’S DRIVING?', 'DNOMYAR NAI.'],
      albumHeading: ['Root Pass', 'Toor Ssap', 'Boot Mass', 'Root Pass?', 'Loot Grass'],
      heroLine: ['Solo acoustic. All original.', 'Not acoustic. Not even human.', 'Written by Ian. Sung by the machine.', 'Take two spoonfuls and call me in the morning.', 'Do not operate heavy machinery.'],
      recordNote: ['Listen from the first song, or find one that speaks to you.', 'The songs are speaking to you. Answer them.', 'Is the bottle glowing, or is it you?', 'This track does not exist. Keep listening.'],
      kicker: ['INDEPENDENT SONGS. TEXAS ROOTS.', 'DEPENDENT SONGS. MARTIAN ROOTS.', 'SIDE B. SIDE C. SIDE Z.', '100% AI. 0% SLEEP.']
    };
    // Letters become individually animated spans; plain text is restored afterwards.
    function letters(el, value) {
      const wrap = document.createElement('span'); wrap.className = 'psy-w';   // one box, so flex containers keep line breaks
      wrap.append(...[...value].map((ch, i) => {
        if (ch === '\n') return document.createTextNode('\n');
        const s = document.createElement('span'); s.className = 'psy-l'; s.textContent = ch; s.style.setProperty('--i', i); return s;
      }));
      el.replaceChildren(wrap);
    }
    function tripTargets() {
      return {artistName: $('artistName'), albumHeading: $('albumHeading'), heroLine: document.querySelector('.hero-line p'),
        recordNote: document.querySelector('.record-note'), kicker: document.querySelector('.hero-kicker span'), cover: $('coverTitle')};
    }
    function tripWordsStep() {
      const el = tripTargets(); tripStep++;
      for (const [key, list] of Object.entries(tripWords)) letters(el[key], list[tripStep % list.length]);
      letters(el.cover, tripStep % 2 ? 'NAKED\nBILLY' : (tripSaved.get(el.cover) || 'ROOT\nPASS'));
    }
    // Animated Julia-set fractal, drawn at low resolution and scaled up; runs only while tripping.
    let fractalFrame = 0;
    function fractal(bpm) {
      const canvas = $('psyFractal'), ctx = canvas.getContext('2d'), started = performance.now();
      const draw = now => {
        if (!tripping) return;
        const w = Math.max(80, Math.round(innerWidth / 5)), h = Math.max(60, Math.round(innerHeight / 5));
        if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; }
        const s = (now - started) / 1000, beat = s * bpm / 60, img = ctx.createImageData(w, h), d = img.data;
        const angle = s * .23, cr = .7885 * Math.cos(angle), ci = .7885 * Math.sin(angle);
        const zoom = 1.35 + .06 * Math.sin(beat * Math.PI * 2) + .25 * Math.sin(s * .11), hueShift = s * 40;
        for (let y = 0, k = 0; y < h; y++) for (let x = 0; x < w; x++, k += 4) {
          let zx = (x - w / 2) / (h / 2) * zoom, zy = (y - h / 2) / (h / 2) * zoom, n = 0;
          while (n < 48 && zx * zx + zy * zy < 4) { const tx = zx * zx - zy * zy + cr; zy = 2 * zx * zy + ci; zx = tx; n++; }
          if (n === 48) { d[k] = d[k + 1] = d[k + 2] = 0; d[k + 3] = 255; continue; }
          const hue = (n * 11 + hueShift) % 360 / 60, c = Math.min(1, n / 18) * 255, xx = c * (1 - Math.abs(hue % 2 - 1));
          const [r, g, b] = hue < 1 ? [c, xx, 0] : hue < 2 ? [xx, c, 0] : hue < 3 ? [0, c, xx] : hue < 4 ? [0, xx, c] : hue < 5 ? [xx, 0, c] : [c, 0, xx];
          d[k] = r; d[k + 1] = g; d[k + 2] = b; d[k + 3] = 255;
        }
        ctx.putImageData(img, 0, 0); fractalFrame = requestAnimationFrame(draw);
      };
      fractalFrame = requestAnimationFrame(draw);
    }
    // Floating night-time cold-medicine imagery (generic, drawn here: bottles, dosing cups, capsules, spoons, moons, Zzz).
    const SPRITES = [
      '<svg viewBox="0 0 32 48"><rect x="10.5" y="1.5" width="11" height="7.5" rx="1.6" fill="#2d1450"/><path d="M12 9h8v4.2c5.2 1.9 8.5 5.2 8.5 10.3v18.7a4.3 4.3 0 0 1-4.3 4.3H7.8a4.3 4.3 0 0 1-4.3-4.3V23.5c0-5.1 3.3-8.4 8.5-10.3z" fill="#8a3ffc"/><rect x="7" y="25" width="18" height="11" rx="1.2" fill="#efe4ff"/><path d="M11 28.5h10M11 32h7" stroke="#8a3ffc" stroke-width="1.6"/></svg>',
      '<svg viewBox="0 0 32 48"><rect x="10.5" y="1.5" width="11" height="7.5" rx="1.6" fill="#0f3d14"/><path d="M12 9h8v4.2c5.2 1.9 8.5 5.2 8.5 10.3v18.7a4.3 4.3 0 0 1-4.3 4.3H7.8a4.3 4.3 0 0 1-4.3-4.3V23.5c0-5.1 3.3-8.4 8.5-10.3z" fill="#38d430"/><rect x="7" y="25" width="18" height="11" rx="1.2" fill="#eaffea"/><path d="M11 28.5h10M11 32h7" stroke="#1d8a17" stroke-width="1.6"/></svg>',
      '<svg viewBox="0 0 40 36"><path d="M4 3h32l-4 30H8z" fill="#c9a6ff" fill-opacity=".55" stroke="#f1e6ff" stroke-width="2"/><path d="M7 16h26l-2.4 15H9.4z" fill="#8a3ffc"/><path d="M8 9h6M8 13h9" stroke="#fff" stroke-width="1.5"/></svg>',
      '<svg viewBox="0 0 48 20"><rect x="1" y="1" width="46" height="18" rx="9" fill="#8a3ffc"/><path d="M24 1h14a9 9 0 0 1 0 18H24z" fill="#38d430"/><path d="M8 6h8" stroke="#fff" stroke-opacity=".6" stroke-width="2" stroke-linecap="round"/></svg>',
      '<svg viewBox="0 0 56 20"><ellipse cx="12" cy="10" rx="10" ry="7" fill="#e7e7f2"/><ellipse cx="12" cy="10" rx="7" ry="4.5" fill="#8a3ffc"/><rect x="20" y="8" width="34" height="4" rx="2" fill="#e7e7f2"/></svg>',
      '<svg viewBox="0 0 40 40"><path d="M27 4a16 16 0 1 0 9 26A14 14 0 0 1 27 4z" fill="#fff35c"/><circle cx="33" cy="9" r="1.6" fill="#fff"/><circle cx="37" cy="17" r="1" fill="#fff"/></svg>',
      '<svg viewBox="0 0 60 40"><text x="0" y="34" font-family="Oswald,Impact,sans-serif" font-size="30" fill="#4ff0ff">Z</text><text x="20" y="22" font-family="Oswald,Impact,sans-serif" font-size="22" fill="#ff4fd8">z</text><text x="36" y="12" font-family="Oswald,Impact,sans-serif" font-size="15" fill="#9dff5c">z</text></svg>'];
    function floaters() {
      const box = $('psyFloat'); if (box.children.length) return;
      for (let i = 0; i < 22; i++) {
        const el = document.createElement('span'); el.className = 'psy-sprite'; el.innerHTML = SPRITES[i % SPRITES.length];
        const r = (a, b) => (a + Math.random() * (b - a)).toFixed(2);
        el.style.cssText = `--x:${r(0, 96)}vw;--drift:${r(-30, 30)}vw;--size:${r(34, 96)}px;--spin:${r(-720, 720)}deg;--dur:${r(9, 22)}s;--delay:-${r(0, 22)}s`;
        box.append(el);
      }
    }
    function trip(on) {
      if (on === tripping) return;
      tripping = on; const root = document.documentElement;
      if (calm.matches) { root.classList.toggle('negative', on); return; }   // reduced motion: calm negative colours only
      const el = tripTargets();
      if (on) {
        const bpm = Number(secret.psychedelic?.bpm) > 0 ? Number(secret.psychedelic.bpm) : 140;
        root.style.setProperty('--beat', (60 / bpm).toFixed(4) + 's');
        for (const node of Object.values(el)) { tripSaved.set(node, node.textContent); letters(node, node.textContent); }
        rows.forEach((row, i) => { row.style.setProperty('--row', i); letters(row.querySelector('.track-title'), tracks[i].title); });
        letters($('nowTitle'), tracks[current].title);
        root.classList.add('psy'); $('psyLayer').hidden = false;
        tripStep = 0; tripTimer = setInterval(tripWordsStep, (60 / bpm) * 4000); floaters(); fractal(bpm);
      } else {
        clearInterval(tripTimer); cancelAnimationFrame(fractalFrame); root.classList.remove('psy'); $('psyLayer').hidden = true;
        for (const [node, value] of tripSaved) node.textContent = value;
        tripSaved.clear();
        rows.forEach((row, i) => { row.querySelector('.track-title').textContent = tracks[i].title; });
        $('nowTitle').textContent = tracks[current].title;
      }
    }
    const announce = message => { $('playerStatus').textContent = message; };
    function playbackOrder() {
      const indices = tracks.map((_, i) => i);
      if (!releases.length) return indices;
      const rank = new Map(releases.map((release, i) => [release.id, i]));
      return indices.sort((a, b) => (rank.get(tracks[a].releaseId) ?? releases.length) - (rank.get(tracks[b].releaseId) ?? releases.length) || a - b);
    }
    function available() { return playbackOrder().filter(i => !failed.has(i)); }
    function stats() {
      const total = tracks.reduce((sum, _, i) => sum + (durations.get(i) || 0), 0);
      $('albumStats').textContent = `${tracks.length} SONG${tracks.length === 1 ? '' : 'S'}${durations.size === tracks.length ? ' / ' + time(total) : ''}`;
    }
    function repaint() {
      const playing = audio && !audio.paused && !audio.ended;
      $('play').setAttribute('aria-label', playing || intent ? 'Pause' : 'Play');
      $('play').title = playing || intent ? 'Pause' : 'Play';
      $('playIcon').innerHTML = playing || intent ? '<path d="M7 5h3v14H7zm7 0h3v14h-3z" fill="currentColor" stroke="none"/>' : '<path d="M8 5v14l11-7z" fill="currentColor" stroke="none"/>';
      $('playState').textContent = tripping ? 'NOW TRIPPING' : playing ? 'NOW PLAYING' : intent ? 'LOADING' : audio && audio.currentTime > 0 ? 'PAUSED' : 'READY WHEN YOU ARE';
      trip(secretIndex >= 0 && current === secretIndex && (playing || intent));
      const empty = !available().length;
      ['play', 'playAlbum', 'previous', 'next', 'shuffle', 'repeat'].forEach(id => { $(id).disabled = empty; });
      rows.forEach((row, i) => {
        row.classList.toggle('current', i === current); row.classList.toggle('unavailable', failed.has(i));
        row.disabled = failed.has(i);
        const control = row.querySelector('button.track-arrow');
        if (control) { control.disabled = failed.has(i); control.setAttribute('aria-label', `${i === current && playing ? 'Pause' : 'Play'} ${tracks[i].title}`); }
        if (i === current) row.setAttribute('aria-current', 'true'); else row.removeAttribute('aria-current');
        row.setAttribute('aria-label', `${failed.has(i) ? 'Unavailable: ' : i === current && playing ? 'Pause: ' : 'Play: '}${tracks[i].title}`);
        row.querySelector('.track-arrow').textContent = i === current && playing ? 'Ⅱ' : '▶';
        row.querySelector('.track-duration').textContent = failed.has(i) ? '—' : time(durations.get(i));
      });
    }
    function progress() {
      if (!audio) return;
      const duration = audio.duration, ready = Number.isFinite(duration) && duration > 0;
      $('seek').disabled = !ready; $('seek').max = ready ? duration : 100;
      $('seek').value = ready ? audio.currentTime : 0;
      $('seek').setAttribute('aria-valuetext', `${time(audio.currentTime)} of ${time(duration)}`);
      $('elapsed').textContent = time(audio.currentTime); $('duration').textContent = time(duration);
    }
    function shuffled(values) {
      const result = values.slice();
      for (let i = result.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [result[i], result[j]] = [result[j], result[i]]; }
      return result;
    }
    function resetBag() { bag = shuffled(available().filter(i => i !== current)); }
    function nextIndex(automatic) {
      const candidates = available(); if (!candidates.length) return null;
      if (automatic && repeat === 'one' && !failed.has(current)) return current;
      if (shuffle) {
        bag = bag.filter(i => !failed.has(i) && i !== current);
        if (!bag.length && (!automatic || repeat === 'all')) resetBag();
        if (bag.length) return bag.shift();
        return repeat === 'all' || !automatic ? candidates[0] : null;
      }
      const order = playbackOrder();
      for (const i of order.slice(order.indexOf(current) + 1)) if (!failed.has(i)) return i;
      return repeat === 'all' || !automatic ? candidates[0] : null;
    }
    function stop(message) { intent = false; clearTimeout(pendingTimer); if (audio) audio.pause(); repaint(); if (message) announce(message); }
    function badTrack(i, a) {
      if (a !== audio) return;
      failed.add(i); clearTimeout(pendingTimer); repaint();
      if (!available().length) { stop('No tracks can be played. Check the files in the music folder, then reopen the player.'); return; }
      const message = `“${tracks[i].title}” is unavailable.`;
      if (intent) {
        const next = nextIndex(true);
        if (next !== null) { announce(message + ' Moving to the next available song.'); load(next, true, true); }
        else stop(message + ' End of album. Select another song to listen.');
      } else announce(message + ' Select another song to listen.');
    }
    async function play() {
      if (!audio) return;
      if (failed.has(current)) {
        const next = nextIndex(false); if (next !== null) load(next, true); return;
      }
      if (showingAlbumCover) { showingAlbumCover = false; cover(tracks[current]); }
      const a = audio; intent = true;
      if (!$('welcomeVideo').paused) $('welcomeVideo').pause();
      if (a.ended) { reports?.restart(a); a.currentTime = 0; }
      clearTimeout(pendingTimer); repaint();
      pendingTimer = setTimeout(() => {
        if (audio === a && intent && a.readyState < 3) announce('Playback is taking longer than expected. You can pause or choose another track.');
      }, 12000);
      try { await a.play(); if (a === audio) { clearTimeout(pendingTimer); repaint(); } }
      catch (error) {
        if (a !== audio || error.name === 'AbortError') return;
        clearTimeout(pendingTimer);
        if (error.name === 'NotSupportedError') badTrack(current, a);
        else { stop('Playback could not start. Click Play to try again, or check your audio output.'); }
      }
    }
    function toggle() { if (!audio) return; if (intent || !audio.paused) stop(''); else { announce(''); play(); } }
    function cover(track) {
      const image = $('coverImage');
      const releaseArt = track.releaseId ? localURL(track.releaseArtwork) : localURL(config.artwork);
      const candidates = [...new Set([localURL(track.artwork), releaseArt].filter(Boolean))];
      if (releases.length) {
        const coverTitle = text(track.releaseTitle) || (track === config ? text(releases[0]?.title) : '') || title;
        $('coverTitle').textContent = coverTitle.toUpperCase().replace(/\s+/, '\n');
        $('coverType').setAttribute('aria-label', `${coverTitle}, ${artist}`);
      }
      image.hidden = true; $('coverType').hidden = false;
      image.onload = () => { image.hidden = false; $('coverType').hidden = true; };
      image.onerror = () => {
        if (candidates.length) image.src = candidates.shift();
        else { image.hidden = true; $('coverType').hidden = false; image.removeAttribute('src'); }
      };
      if (candidates.length) { image.alt = `${track.title} · ${artist}`; image.src = candidates.shift(); }
      else image.removeAttribute('src');
      // Album graphic in the system media controls (lock screen, taskbar, media keys) while the song plays.
      try {
        if ('mediaSession' in navigator && window.MediaMetadata) {
          const art = localURL(track.artwork) || releaseArt;
          navigator.mediaSession.metadata = new MediaMetadata({ title: track.title, artist, album: text(track.releaseTitle) || text(config.title),
            artwork: art ? [{ src: art, sizes: '1254x1254', type: 'image/jpeg' }] : [] });
        }
      } catch (error) { console.warn(error); }
    }
    function notes(track) {
      const container = $('noteContent'); container.replaceChildren();
      for (const [key, label] of [['story', 'The story'], ['lyrics', 'Lyrics'], ['credits', 'Credits']]) {
        if (!text(track[key])) continue;
        const block = document.createElement('details'), heading = document.createElement('summary');
        heading.textContent = label; block.append(heading, paragraph(track[key])); container.append(block);
      }
      $('songNotes').hidden = !container.children.length;
      $('notesHeading').textContent = `Inside “${track.title}”`;
    }
    function load(i, autoplay = false, preserveMessage = false, remember = true) {
      if (!tracks[i]) return;
      if (audio && remember && current !== i) history.push(current);
      if (audio) { audio.pause(); audio.removeAttribute('src'); audio.load(); }
      clearTimeout(pendingTimer); current = i; intent = autoplay;
      const a = new Audio(); audio = a; reports?.watch(a, tracks[i]); a.preload = 'metadata'; a.volume = volume; a.muted = muted;
      a.addEventListener('loadedmetadata', () => {
        if (a !== audio) return;
        if (Number.isFinite(a.duration)) durations.set(i, a.duration); stats(); progress(); repaint();
      });
      a.addEventListener('timeupdate', () => { if (a === audio) { progress(); revealSecret(a); } });
      a.addEventListener('playing', () => { if (a === audio) { intent = true; clearTimeout(pendingTimer); repaint(); } });
      a.addEventListener('pause', () => { if (a === audio) repaint(); });
      a.addEventListener('ended', () => {
        if (a !== audio) return;
        const next = nextIndex(true);
        if (next !== null) { if (failed.size) announce('Unavailable tracks are being skipped.'); load(next, true, failed.size > 0); } else stop(releases.length > 1 ? 'End of playlist. Thanks for listening.' : 'End of album. Thanks for listening.');
      });
      a.addEventListener('error', () => badTrack(i, a));
      $('nowTitle').textContent = tracks[i].title;
      $('nowArtist').textContent = [artist, text(tracks[i].releaseTitle)].filter(Boolean).join(' · ');
      $('elapsed').textContent = '0:00';
      $('duration').textContent = time(durations.get(i)); $('seek').value = 0; $('seek').disabled = true;
      if (!showingAlbumCover) cover(tracks[i]);
      notes(tracks[i]); if (!preserveMessage) announce('');
      a.src = localURL(tracks[i].file); repaint(); if (autoplay) play();
    }
    function addRow(i) {
      const track = tracks[i], li = document.createElement('li');
      const page = web && webURL(track.pageUrl);
      const row = document.createElement(page ? 'div' : 'button');
      if (page) row.setAttribute('role', 'group'); else row.type = 'button'; row.className = i === secretIndex ? 'track-row secret' : 'track-row'; row.dataset.trackId = text(track.id) || String(i);
      for (const [className, value] of [['track-number', String(track.releaseNumber || i + 1).padStart(2, '0')], ['track-title', track.title], ['track-duration', '—:—'], ['track-arrow', '▶']]) {
        const span = document.createElement(page && className === 'track-title' ? 'a' : page && className === 'track-arrow' ? 'button' : 'span');
        span.className = className; span.textContent = value;
        if (page && className === 'track-title') { span.href = page; span.addEventListener('click', event => event.stopPropagation()); }
        if (page && className === 'track-arrow') span.type = 'button';
        else if (className === 'track-arrow') span.setAttribute('aria-hidden', 'true');
        row.append(span);
      }
      row.addEventListener('click', () => { if (failed.has(i)) return; if (current === i) toggle(); else { load(i, true); if (shuffle) resetBag(); } });
      li.append(row); (releaseLists.get(track.releaseId) || $('trackList')).append(li); rows.push(row);
    }
    for (const release of releases) {
      const item = document.createElement('li'); item.className = 'release-section'; item.dataset.releaseId = release.id;
      const header = document.createElement('div'); header.className = 'release-header';
      const art = localURL(release.artwork);
      if (art) {
        const img = document.createElement('img'); img.src = art; img.alt = `${release.title} cover`; img.loading = 'lazy';
        img.addEventListener('error', () => { img.hidden = true; }); header.append(img);
      }
      const details = document.createElement('div'), heading = document.createElement('h3'); heading.textContent = release.title;
      const kind = paragraph(release.type === 'single' ? 'SINGLE' : 'ALBUM', 'eyebrow'); details.append(kind, heading); header.append(details);
      const list = document.createElement('ol'); list.className = 'release-tracks'; list.setAttribute('aria-label', `${release.title} songs`);
      item.append(header, list); $('trackList').append(item); releaseLists.set(release.id, list);
    }
    tracks.forEach((_, i) => addRow(i));
    function revealSecret(a) {
      if (!secretTrack || secretShown || secretIndex >= 0 || !secretTrigger || text(tracks[current].id) !== secretTrigger || a.currentTime < secretDelay) return;
      secretShown = true; $('secretBottle').hidden = false;
      announce('Something appeared above the player. Select the purple bottle.');
    }
    function unlockSecret() {
      if (secretIndex >= 0) return;
      secretIndex = tracks.push(secretTrack) - 1; addRow(secretIndex); probe(secretIndex);
      if (shuffle) bag.splice(Math.floor(Math.random() * (bag.length + 1)), 0, secretIndex);
      $('secretBottle').hidden = true; $('secretDialog').close(); stats(); repaint();
      rows[secretIndex].focus();
      announce(`“${secretTrack.title}” was added to the end of ${text(secretTrack.releaseTitle) || 'the playlist'}.`);
    }
    function initSecret() {
      if (!secretTrack) return;
      const dialog = $('secretDialog'), body = $('secretBody');
      $('secretTitle').textContent = text(secretNote.title) || `Secret Song: ${secretTrack.title}`;
      for (const item of Array.isArray(secretNote.paragraphs) ? secretNote.paragraphs : []) {
        const p = paragraph(typeof item === 'string' ? item : text(item.text), 'secret-text');
        if (item && text(item.lead)) { const lead = document.createElement('strong'); lead.textContent = text(item.lead) + ' '; p.prepend(lead); }
        body.append(p);
      }
      $('secretAdd').textContent = text(secretNote.addLabel) || `Add “${secretTrack.title}” to the playlist`;
      $('secretClose').textContent = text(secretNote.closeLabel) || 'Close';
      $('secretCaption').textContent = text(secret.caption) || 'Click here';
      $('secretBottle').addEventListener('click', () => dialog.showModal());
      $('secretAdd').addEventListener('click', unlockSecret);
      $('secretClose').addEventListener('click', () => dialog.close());
      dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
    }
    $('play').addEventListener('click', toggle);
    $('playAlbum').addEventListener('click', () => {
      const first = available()[0]; if (first === undefined) return; history = []; load(first, true); if (shuffle) resetBag();
    });
    $('next').addEventListener('click', () => { const next = nextIndex(false); if (next !== null) load(next, true); });
    $('previous').addEventListener('click', () => {
      if (audio && audio.currentTime > 3 && !failed.has(current)) { audio.currentTime = 0; progress(); return; }
      let prev = history.pop(); while (prev !== undefined && failed.has(prev)) prev = history.pop();
      if (prev === undefined) {
        const order = playbackOrder(), position = order.indexOf(current);
        for (let offset = 1; offset <= order.length; offset++) { const candidate = order[(position - offset + order.length) % order.length]; if (!failed.has(candidate)) { prev = candidate; break; } }
      }
      if (prev !== undefined) { load(prev, true, false, false); if (shuffle) resetBag(); }
    });
    $('shuffle').addEventListener('click', () => { shuffle = !shuffle; $('shuffle').setAttribute('aria-pressed', String(shuffle)); resetBag(); announce(`Shuffle ${shuffle ? 'on' : 'off'}.`); });
    $('repeat').addEventListener('click', () => {
      repeat = ({off: 'all', all: 'one', one: 'off'})[repeat];
      $('repeat').setAttribute('aria-pressed', String(repeat !== 'off'));
      const label = `Repeat: ${repeat === 'all' ? (releases.length > 1 ? 'all releases' : 'album') : repeat === 'one' ? 'current song' : 'off'}`;
      $('repeat').setAttribute('aria-label', label); $('repeat').title = label; $('repeatOne').hidden = repeat !== 'one'; announce(label + '.');
    });
    $('seek').addEventListener('input', () => { if (audio && Number.isFinite(audio.duration)) { audio.currentTime = Number($('seek').value); progress(); } });
    function volumeUI() {
      if (audio) { audio.volume = volume; audio.muted = muted; }
      const silent = muted || volume === 0; $('mute').setAttribute('aria-pressed', String(silent));
      $('mute').setAttribute('aria-label', silent ? 'Unmute' : 'Mute'); $('mute').title = silent ? 'Unmute' : 'Mute';
      $('volumeValue').textContent = silent ? 'Muted' : Math.round(volume * 100) + '%';
      $('volume').setAttribute('aria-valuetext', Math.round(volume * 100) + '%');
    }
    $('volume').addEventListener('input', () => { volume = Number($('volume').value); muted = false; volumeUI(); });
    $('mute').addEventListener('click', () => {
      if (muted || volume === 0) { muted = false; if (volume === 0) { volume = 1; $('volume').value = 1; } } else muted = true;
      volumeUI();
    });
    document.addEventListener('keydown', event => {
      if (event.code === 'Space' && (event.target === document.body || event.target === document.documentElement) && !$('documentDialog').open) { event.preventDefault(); toggle(); }
    });
    initExtras(config); initSecret();
    volumeUI(); stats();
    if (!tracks.length) { fatal('No playable track entries were supplied.'); $('playState').textContent = 'NO TRACKS'; return; }
    $('loadMessage').textContent = tracks.length === config.tracks.length ? '' : 'Some track entries could not be loaded. Check the content index.';
    cover(config); load(0);
    // Metadata reads do not play audio and always use the files currently in the package.
    function probe(i) {
      const track = tracks[i], probe = new Audio(); probes.add(probe); probe.preload = 'metadata';
      const release = () => { probe.onloadedmetadata = probe.onerror = null; probe.removeAttribute('src'); probe.load(); probes.delete(probe); };
      probe.onloadedmetadata = () => { if (Number.isFinite(probe.duration)) durations.set(i, probe.duration); stats(); repaint(); release(); };
      probe.onerror = () => {
        failed.add(i);
        if (!available().length) stop('No tracks can be played. Check the files in the music folder, then reopen the player.');
        else repaint();
        release();
      };
      probe.src = localURL(track.file);
    }
    tracks.forEach((_, i) => probe(i));
    window.addEventListener('pagehide', () => { clearTimeout(pendingTimer); if (audio) audio.pause(); probes.forEach(a => { a.removeAttribute('src'); a.load(); }); });

    function initExtras(data) {
      const welcome = data.welcome || {}, video = $('welcomeVideo');
      $('welcomeText').textContent = text(welcome.text); $('welcomeText').hidden = !text(welcome.text);
      const videoPath = localURL(welcome.video);
      if (videoPath) {
        video.src = videoPath; video.hidden = false;
        const poster = localURL(welcome.poster); if (poster) video.poster = poster;
        video.addEventListener('play', () => stop('Music paused for the introduction.'));
        video.addEventListener('error', () => { video.hidden = true; $('videoError').hidden = false; });
      }
      $('welcome').hidden = !text(welcome.text) && !videoPath;
      const dialog = $('documentDialog');
      $('closeDocument').addEventListener('click', () => dialog.close());
      dialog.addEventListener('click', e => { if (e.target === dialog && (e.clientX < dialog.getBoundingClientRect().left || e.clientX > dialog.getBoundingClientRect().right || e.clientY < dialog.getBoundingClientRect().top || e.clientY > dialog.getBoundingClientRect().bottom)) dialog.close(); });
      dialog.addEventListener('close', () => { if (!dialog.open) $('documentBody').replaceChildren(); });
      function docButton(label, path) {
        const url = localURL(path); if (!url) return;
        const button = document.createElement('button'); button.className = 'text-button'; button.textContent = label + ' ↗';
        button.addEventListener('click', () => {
          $('documentTitle').textContent = label; const body = $('documentBody'); body.replaceChildren();
          const open = externalLink('Open document in a separate tab ↗', url); open.className = 'text-link'; body.append(open);
          const object = document.createElement('object'); object.type = 'application/pdf'; object.data = url;
          object.setAttribute('aria-label', label);
          object.append(paragraph('This document could not be previewed. Try the link above. If the file is unavailable, you can close this window and keep listening.'));
          body.append(object); dialog.showModal();
        });
        $('documentLinks').append(button);
      }
      const booklet = data.booklet || {}, sections = Array.isArray(booklet.sections) ? booklet.sections.filter(s => s && (text(s.text) || localURL(s.image))) : [];
      if (localURL(booklet.file)) docButton(text(booklet.title) || 'Album booklet', booklet.file);
      else if (text(booklet.intro) || sections.length) {
        const button = document.createElement('button'); button.className = 'text-button'; button.textContent = 'Read the album booklet ↗';
        button.addEventListener('click', () => {
          $('documentTitle').textContent = text(booklet.title) || 'Album booklet'; const body = $('documentBody'); body.replaceChildren();
          if (text(booklet.intro)) body.append(paragraph(booklet.intro));
          for (const section of sections) {
            const article = document.createElement('section'); article.className = 'booklet-section';
            if (text(section.heading)) { const h = document.createElement('h3'); h.textContent = section.heading; article.append(h); }
            if (localURL(section.image)) {
              const img = document.createElement('img'); img.src = localURL(section.image); img.alt = text(section.caption);
              img.onerror = () => img.remove(); article.append(img);
            }
            if (text(section.text)) article.append(paragraph(section.text));
            if (text(section.caption)) article.append(paragraph(section.caption, 'small')); body.append(article);
          }
          const print = document.createElement('button'); print.textContent = 'Print / save booklet'; print.className = 'text-button'; print.addEventListener('click', () => window.print()); body.append(print);
          dialog.showModal();
        }); $('documentLinks').append(button);
      }
      const epk = data.epk || {}; docButton('Press kit · offline', epk.file);
      if (webURL(epk.url)) { const link = externalLink('Press kit · online ↗', webURL(epk.url)); link.className = 'text-button'; $('documentLinks').append(link); }
      $('inside').hidden = $('welcome').hidden && !$('documentLinks').children.length; $('insideNav').hidden = $('inside').hidden;
      const socials = Array.isArray(data.socialLinks) ? data.socialLinks : [];
      for (const social of socials) if (social && text(social.label) && webURL(social.url)) $('socialLinks').append(externalLink(social.label + ' ↗', webURL(social.url)));
      const booking = data.booking || {};
      if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text(booking.email))) {
        $('bookingLink').href = 'mailto:' + encodeURIComponent(booking.email) + '?subject=Booking%20inquiry';
        $('bookingLink').textContent = text(booking.label) || booking.email; $('bookingLink').hidden = false;
      }
      const website = data.website || {}, websiteURL = webURL(website.url);
      if (websiteURL) {
        $('websiteCard').hidden = false; $('websiteLink').href = websiteURL;
        $('websiteLink').textContent = (text(website.label) || new URL(websiteURL).hostname) + ' ↗';
        if (webURL(website.qrDestination) === websiteURL && localURL(website.qr)) {
          $('websiteQR').src = localURL(website.qr); $('websiteQR').hidden = false;
          $('websiteQR').onerror = () => { $('websiteQR').hidden = true; };
        }
      }
      $('connect').hidden = !$('socialLinks').children.length && $('bookingLink').hidden && $('websiteCard').hidden;
      $('connectNav').hidden = $('connect').hidden;
    }
  }
})();
