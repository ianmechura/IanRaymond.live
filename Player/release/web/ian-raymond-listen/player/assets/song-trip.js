/* NyQuil song page: the same easter egg as the main player — after a few seconds of NyQuil a purple bottle appears;
   its note plays the secret song (Naked Billy) and that starts the trip, escalating with the secret song: crash,
   colour worlds, then the syrup bottles turn into bombs that blow the page around and sometimes tear holes back to
   reality. Pausing stops it; seeking jumps to the matching stage; when the secret song ends the page returns to NyQuil.
   SPRITES is copied from player.js by build_web.py so the medicine art has one source. */
(() => {
  'use strict';
  const page = document.querySelector('.irl-song[data-trip]'), audio = page?.querySelector('audio');
  if (!page || !audio) return;
  const root = document.documentElement, calm = matchMedia('(prefers-reduced-motion: reduce)');
  const bpm = Number(page.dataset.bpm) > 0 ? Number(page.dataset.bpm) : 140;
  root.style.setProperty('--beat', (60 / bpm).toFixed(4) + 's');
  const secret = (() => { try { return JSON.parse(document.getElementById('irl-secret')?.textContent || 'null'); } catch { return null; } })();
  if (!secret?.file) return;
  const SPRITES = [
      '<svg viewBox="0 0 32 48"><rect x="10.5" y="1.5" width="11" height="7.5" rx="1.6" fill="#2d1450"/><path d="M12 9h8v4.2c5.2 1.9 8.5 5.2 8.5 10.3v18.7a4.3 4.3 0 0 1-4.3 4.3H7.8a4.3 4.3 0 0 1-4.3-4.3V23.5c0-5.1 3.3-8.4 8.5-10.3z" fill="#8a3ffc"/><rect x="7" y="25" width="18" height="11" rx="1.2" fill="#efe4ff"/><path d="M11 28.5h10M11 32h7" stroke="#8a3ffc" stroke-width="1.6"/></svg>',
      '<svg viewBox="0 0 32 48"><rect x="10.5" y="1.5" width="11" height="7.5" rx="1.6" fill="#0f3d14"/><path d="M12 9h8v4.2c5.2 1.9 8.5 5.2 8.5 10.3v18.7a4.3 4.3 0 0 1-4.3 4.3H7.8a4.3 4.3 0 0 1-4.3-4.3V23.5c0-5.1 3.3-8.4 8.5-10.3z" fill="#38d430"/><rect x="7" y="25" width="18" height="11" rx="1.2" fill="#eaffea"/><path d="M11 28.5h10M11 32h7" stroke="#1d8a17" stroke-width="1.6"/></svg>',
      '<svg viewBox="0 0 40 36"><path d="M4 3h32l-4 30H8z" fill="#c9a6ff" fill-opacity=".55" stroke="#f1e6ff" stroke-width="2"/><path d="M7 16h26l-2.4 15H9.4z" fill="#8a3ffc"/><path d="M8 9h6M8 13h9" stroke="#fff" stroke-width="1.5"/></svg>',
      '<svg viewBox="0 0 48 20"><rect x="1" y="1" width="46" height="18" rx="9" fill="#8a3ffc"/><path d="M24 1h14a9 9 0 0 1 0 18H24z" fill="#38d430"/><path d="M8 6h8" stroke="#fff" stroke-opacity=".6" stroke-width="2" stroke-linecap="round"/></svg>',
      '<svg viewBox="0 0 56 20"><ellipse cx="12" cy="10" rx="10" ry="7" fill="#e7e7f2"/><ellipse cx="12" cy="10" rx="7" ry="4.5" fill="#8a3ffc"/><rect x="20" y="8" width="34" height="4" rx="2" fill="#e7e7f2"/></svg>',
      '<svg viewBox="0 0 40 40"><path d="M27 4a16 16 0 1 0 9 26A14 14 0 0 1 27 4z" fill="#fff35c"/><circle cx="33" cy="9" r="1.6" fill="#fff"/><circle cx="37" cy="17" r="1" fill="#fff"/></svg>',
      '<svg viewBox="0 0 60 40"><text x="0" y="34" font-family="Oswald,Impact,sans-serif" font-size="30" fill="#4ff0ff">Z</text><text x="20" y="22" font-family="Oswald,Impact,sans-serif" font-size="22" fill="#ff4fd8">z</text><text x="36" y="12" font-family="Oswald,Impact,sans-serif" font-size="15" fill="#9dff5c">z</text></svg>'];
  const BOTTLE = '<svg viewBox="0 0 32 48" aria-hidden="true"><rect class="cap" x="10.5" y="1.5" width="11" height="7.5" rx="1.6"/><path class="glass" d="M12 9h8v4.2c5.2 1.9 8.5 5.2 8.5 10.3v18.7a4.3 4.3 0 0 1-4.3 4.3H7.8a4.3 4.3 0 0 1-4.3-4.3V23.5c0-5.1 3.3-8.4 8.5-10.3z"/><rect class="label" x="7" y="25" width="18" height="11" rx="1.2"/><path class="shine" d="M8.6 15.5c-1.8 1.8-2.7 3.9-2.7 6.6"/></svg>';
  const BOMB = '<svg viewBox="0 0 64 72"><path d="M41 21c3-9 9-12 14-9" fill="none" stroke="#c9a46a" stroke-width="3" stroke-linecap="round"/><g class="st-spark"><path d="M56 4l2 6 6 1-5 4 2 6-5-3-5 3 2-6-5-4 6-1z" fill="#ffe600"/><circle cx="56" cy="12" r="2.4" fill="#fff"/></g><rect x="34" y="17" width="13" height="9" rx="2" transform="rotate(25 40 21)" fill="#3a3a3a"/><circle cx="30" cy="45" r="25" fill="#161616"/><ellipse cx="21" cy="34" rx="7" ry="4.5" transform="rotate(-35 21 34)" fill="#fff" fill-opacity=".35"/><ellipse cx="22" cy="46" rx="5" ry="6.5" fill="#fff"/><ellipse cx="37" cy="46" rx="5" ry="6.5" fill="#fff"/><circle cx="23.5" cy="48" r="2.6" fill="#000"/><circle cx="35.5" cy="48" r="2.6" fill="#000"/><path d="M15 38l11 4M44 38l-11 4" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/><path d="M23 59q7-5 14 0" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>';
  const WORDS = {
    title: ['Naked Billy', 'B-Side Remix', 'NyQuil', 'QuilNy', 'NAKED BILLY', 'NyQu1l', 'NIGHT NIGHT', 'yllıᗺ pǝʞɐN'],
    artist: ['Ian Raymond', 'Naked Billy', 'Ian Rayyyymond', 'Who’s singing?', 'Ian Raymond (asleep)'],
    release: ['Root Pass', 'Toor Ssap', 'Boot Mass', 'Loot Grass']
  };
  const BOOMS = ['KABOOM!', 'POW!', 'BLAM!', 'FIZZ!', 'KA-BLOOEY!', 'BONK!', 'ACHOO!'];
  const FOREIGN = ['ブーム!', 'БАБАХ!', 'ΜΠΑΜ!', '¡ʍO∀ᗺ', '爆!', 'ＢＯＯＭ！', 'ПУФ!', 'ドカーン!', 'Ƨ˥ƎƎd'];
  const GLYPHS = {A: 'ΛАДᗅ∀', B: 'БΒßᗺ', C: 'СϹↃ', D: 'ᗪÐ', E: 'ΞЕƎ∑', F: 'Ғϝ', H: 'НΗ', I: 'ІΙ¡', K: 'КΚҜ', L: 'Ⱡ⅃˥', M: 'МΜ', N: 'ИΝ', O: 'ОΘ0Ө◎', P: 'РΡԀ', S: 'ЅƧ$', T: 'ТΤ', U: 'ЦᑌՍ', W: 'ШЩʍ', Y: 'УҮ', Z: 'ΖƵ'};
  const MARKS = [...'\u0300\u0301\u0303\u0308\u030A\u0334\u0335\u0336\u0337\u0338\u0352\u035C\u0361'];
  const pick = list => list[Math.floor(Math.random() * list.length)];
  // Blast words come out garbled: lookalike letters from other alphabets plus stray combining marks, or a foreign word.
  function blastWord() {
    if (Math.random() < .35) return pick(FOREIGN);
    return [...pick(BOOMS)].map(ch => {
      let c = GLYPHS[ch] && Math.random() < .55 ? pick([...GLYPHS[ch]]) : ch;
      if (/\S/.test(c) && Math.random() < .35) c += pick(MARKS);
      return c;
    }).join('');
  }
  const WORLDS = ['st-negative', 'st-thermal', 'st-noir', 'st-xray', ''];
  const targets = {title: page.querySelector('h1'), artist: page.querySelector('.artist'), release: page.querySelector('.eyebrow a')};
  const saved = new Map(), timers = new Set();
  const base = {title: targets.title?.textContent || '', song: audio.currentSrc || audio.getAttribute('src')};
  const fired = new Set(); // peaks whose bomb has dropped this play
  let mode = 'song', shown = false, bottle = null, note = null, pristine = null, bodyBg = '';
  let stopped = false, stage = 'normal', world = '', layer = null, frame = 0, wordTimer = 0, bombTimer = 0, step = 0, lastFlash = 0, caption = null, stopButton = null;

  const later = (fn, ms) => { const id = setTimeout(() => { timers.delete(id); fn(); }, ms); timers.add(id); return id; };
  const el = (tag, cls, html) => { const n = document.createElement(tag); if (cls) n.className = cls; if (html) n.innerHTML = html; return n; };
  const rand = (a, b) => a + Math.random() * (b - a);

  // Timeline of the secret song (seconds; the trip starts with it, like the main player). Tuned to Naked Billy (3:41):
  // the crash lands on the first peak, regular bombs start on the third, intensity builds until the calm.
  const CUE = {crash: 29, worlds: 33, bombs: 55, calm: 208};
  // The song's peaks [seconds, level]. Each drops a bomb timed to explode on the peak; level = Ian's "+" marks (major peaks)
  // and makes the bomb, the blast and its window back to reality bigger.
  const PEAKS = [[41, 0], [55, 0], [83, 0], [98, 1], [140, 0], [143, 2], [160, 3], [175, 0], [189, 0], [200, 4]];
  const PEAK_SIZE = [90, 125, 155, 190, 235];
  function stageAt(t, d) {
    const end = Number.isFinite(d) && d > 120 ? d : 221;
    if (t < CUE.crash) return 'trip';
    if (t < CUE.worlds) return 'crash';
    if (t >= Math.min(CUE.calm, end - 10)) return 'comedown';
    return t < CUE.bombs ? 'worlds' : 'bombs';
  }
  const worldAt = t => WORLDS[Math.floor((t - 34) / 14) % WORLDS.length];

  function letters(node, value) {
    if (!node) return;
    const wrap = el('span', 'st-w');
    wrap.append(...[...value].map((ch, i) => { const s = el('span', 'st-l'); s.textContent = ch; s.style.setProperty('--i', i); return s; }));
    node.replaceChildren(wrap);
  }
  function say(text) {
    caption?.remove(); caption = null;
    if (text) { caption = el('div', 'st-caption st-fx'); caption.textContent = text; document.body.append(caption); }
  }

  function fractal() {
    const canvas = layer.querySelector('canvas'), ctx = canvas.getContext('2d'), started = performance.now();
    const draw = now => {
      if (!layer) return;
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
      ctx.putImageData(img, 0, 0); frame = requestAnimationFrame(draw);
    };
    frame = requestAnimationFrame(draw);
  }

  function tripOn() {
    layer = el('div', 'st-layer', '<canvas></canvas><i></i><i></i>'); layer.setAttribute('aria-hidden', 'true');
    for (let i = 0; i < 18; i++) {
      const s = el('span', 'st-sprite', SPRITES[i % (SPRITES.length || 1)] || '');
      s.style.cssText = `--x:${rand(0, 96).toFixed(1)}vw;--drift:${rand(-30, 30).toFixed(1)}vw;--size:${rand(34, 92).toFixed(0)}px;--spin:${rand(-720, 720).toFixed(0)}deg;--dur:${rand(9, 22).toFixed(1)}s;--delay:-${rand(0, 22).toFixed(1)}s`;
      s.dataset.kind = i % (SPRITES.length || 1) < 2 ? 'bottle' : 'other';
      layer.append(s);
    }
    document.body.append(layer);
    for (const [key, node] of Object.entries(targets)) if (node) { saved.set(node, node.textContent); letters(node, node.textContent); }
    root.classList.add('st-on'); fractal();
    step = 0; wordTimer = setInterval(() => { step++; for (const [key, list] of Object.entries(WORDS)) letters(targets[key], list[step % list.length]); }, (60 / bpm) * 4000);
    if (!stopButton) {
      stopButton = el('button', 'st-stop st-fx'); stopButton.type = 'button'; stopButton.textContent = 'Make it stop';
      stopButton.addEventListener('click', () => { stopped = true; tripOff(); root.classList.remove('st-calm'); stopButton.remove(); stopButton = null; });
      document.body.append(stopButton);
    }
  }
  function tripOff() {
    cancelAnimationFrame(frame); clearInterval(wordTimer); clearTimeout(bombTimer); bombTimer = 0;
    for (const id of timers) clearTimeout(id); timers.clear();
    layer?.remove(); layer = null;
    document.querySelectorAll('.st-bomb,.st-boom,.st-scorch,.st-flash,.st-concuss,.st-banner,.st-real').forEach(n => n.remove());
    document.querySelectorAll('.st-hit,.st-back,.st-singed').forEach(n => { n.classList.remove('st-hit', 'st-back', 'st-singed'); n.style.translate = n.style.rotate = ''; });
    for (const [node, value] of saved) node.textContent = value;
    saved.clear(); say('');
    root.classList.remove('st-on', 'st-shake', 'st-bump', ...WORLDS.filter(Boolean)); world = '';
  }
  function setWorld(next) {
    if (next === world) return;
    if (world) root.classList.remove(world);
    if (next) root.classList.add(next);
    world = next;
  }

  function flash(strength) {
    const now = performance.now();
    if (now - lastFlash < 500) return; // never more than two full-screen flashes a second
    lastFlash = now;
    const f = el('div', 'st-flash st-fx'); f.style.opacity = strength; document.body.append(f); later(() => f.remove(), 500);
  }
  function bump() { root.classList.remove('st-bump'); void root.offsetWidth; root.classList.add('st-bump'); later(() => root.classList.remove('st-bump'), 300); }
  function crash() {
    flash(1); root.classList.add('st-shake'); later(() => root.classList.remove('st-shake'), 1400);
    const banner = el('div', 'st-banner st-fx'); banner.textContent = 'DO NOT OPERATE HEAVY MACHINERY'; document.body.append(banner);
    later(() => banner.remove(), 2600);
  }

  // Bombs: a floating bottle pops and a bomb drops from it, falls and explodes.
  // Background bomb rate rises steadily from the first bombs until the calm.
  const ramp = (t, a, b) => Math.min(1, Math.max(0, (t - a) / (b - a)));
  function intensity(t) {
    const k = ramp(t, CUE.bombs, CUE.calm);
    return {gap: 2400 - 2120 * Math.pow(k, .8), max: Math.round(5 + 11 * k)};
  }
  function bombLoop() {
    if (!layer || stage !== 'bombs') return;
    const {gap, max} = intensity(audio.currentTime);
    dropBomb({max});
    bombTimer = later(bombLoop, gap * rand(.75, 1.25));
  }
  // Peak bombs drop a little early, fall into place, flash rainbow while they wait, and explode on the peak (audio clock).
  function dropPeak(at, level) { dropBomb({peakAt: at, level}); }
  function dropBomb({max = 8, peakAt = null, level = 0} = {}) {
    const peak = peakAt !== null;
    if (!peak && document.querySelectorAll('.st-bomb').length >= max) return;
    const content = page.getBoundingClientRect();
    let x = rand(40, innerWidth - 40), y = -60;
    if (peak) {
      x = Math.min(innerWidth - 120, Math.max(120, content.left + content.width * (level ? rand(.35, .65) : rand(.1, .9)))); y = -PEAK_SIZE[level];
    } else {
      const bottles = [...layer.querySelectorAll('.st-sprite[data-kind="bottle"]:not(.st-pop)')].filter(s => { const r = s.getBoundingClientRect(); return r.bottom > 0 && r.top < innerHeight * .7; });
      const bottle = bottles[Math.floor(Math.random() * bottles.length)];
      if (bottle) {
        const r = bottle.getBoundingClientRect(); x = r.left + r.width / 2; y = r.top + r.height / 2;
        bottle.classList.add('st-pop'); later(() => bottle.classList.remove('st-pop'), 4000);
      }
    }
    const size = peak ? PEAK_SIZE[level] : rand(48, 78), bomb = el('div', 'st-bomb st-fx', BOMB); bomb.style.setProperty('--size', size + 'px'); document.body.append(bomb);
    const rainbow = peak || Math.random() < .3; // some ordinary bombs flash before they blow too
    let vx = peak ? rand(-.4, .4) : rand(-2.2, 2.2), vy = peak ? 0 : rand(-6, -2), spin = peak ? rand(-1.5, 1.5) : rand(-8, 8), angle = 0, last = performance.now();
    const fuse = peak ? Math.min(innerHeight * .65, Math.max(innerHeight * .3, content.top + content.height * rand(.3, .6))) : rand(.35, .92) * innerHeight;
    const blow = () => { if (bomb.isConnected) { bomb.remove(); explode(x, y, level, peak); } };
    const wait = () => { if (!bomb.isConnected) return; if (audio.currentTime >= peakAt || audio.currentTime < peakAt - 6) blow(); else requestAnimationFrame(wait); };
    // Real-time physics in 60 fps steps, so bombs fall at the same speed on slow machines (and peak bombs land in time).
    const g = peak ? .3 : .38;
    const fall = now => {
      if (!bomb.isConnected) return;
      let frames = Math.min(90, (now - last) / 16.7), landed = false; last = now;
      while (frames > 0 && !landed) {
        const dt = Math.min(1, frames); frames -= dt;
        vy += g * dt; x += vx * dt; y += vy * dt; angle += spin * dt;
        landed = (vy > 0 && y >= fuse) || y > innerHeight - 30;
      }
      bomb.style.transform = `translate(${x - size / 2}px,${y - size / 2}px) rotate(${angle}deg)`;
      if (landed) {
        if (!rainbow) { blow(); return; }
        bomb.classList.add('st-rainbow');
        if (peak) wait(); else later(blow, rand(700, 1100));
        return;
      }
      requestAnimationFrame(fall);
    };
    requestAnimationFrame(fall);
  }
  // Level 0 = ordinary; peak bombs scale everything with their level (the number of "+").
  function explode(x, y, level = 0, peak = false) {
    const k = 1 + .45 * level + (peak ? .25 : 0);
    const boom = el('div', 'st-boom st-fx'); boom.style.left = x + 'px'; boom.style.top = y + 'px';
    const sparks = Math.round(12 + 4 * level);
    for (let i = 0; i < sparks; i++) {
      const p = el('b'), a = i / sparks * Math.PI * 2 + rand(-.2, .2), dist = rand(70, 150) * k;
      p.style.setProperty('--dx', (Math.cos(a) * dist).toFixed(0) + 'px'); p.style.setProperty('--dy', (Math.sin(a) * dist).toFixed(0) + 'px');
      if (k > 1) { const d = Math.round(28 * k); p.style.cssText += `;width:${d}px;height:${d}px;left:${-d / 2}px;top:${-d / 2}px`; }
      boom.append(p);
    }
    const ring = el('i'); if (k > 1) { const d = Math.round(120 * k); ring.style.cssText = `width:${d}px;height:${d}px;left:${-d / 2}px;top:${-d / 2}px`; }
    const word = el('em'); word.textContent = blastWord(); if (k > 1) word.style.fontSize = Math.round(46 * k) + 'px';
    boom.append(ring, word);
    const scorch = el('div', 'st-scorch st-fx'), sd = Math.round(180 * k);
    scorch.style.cssText = `left:${x}px;top:${y}px;width:${sd}px;height:${sd}px;margin:${-sd / 2}px 0 0 ${-sd / 2}px`;
    document.body.append(scorch, boom); later(() => boom.remove(), 900 + 200 * level); later(() => scorch.remove(), 4000 + 600 * level);
    knockback(x, y, k); concuss(level);
    // Major peaks always tear a hole back to reality; some blasts over the song's content (so there is something real to see) do too.
    const content = page.getBoundingClientRect();
    if (level) reality(x, y, level);
    else if (x > content.left && x < content.right && y > content.top && y < content.bottom && Math.random() < (peak ? .6 : .4)) reality(x, y);
  }
  // A hole back to reality: the pristine page (copied before the trip) shows through a circle that opens and closes.
  function snapshot() {
    bodyBg = getComputedStyle(document.body).backgroundColor;
    pristine = [...document.body.children].filter(n => !n.matches('script,style,link,dialog,.st-fx,.st-layer,#wpadminbar')).map(n => {
      const c = n.cloneNode(true);
      c.querySelectorAll('[id]').forEach(e => e.removeAttribute('id')); c.removeAttribute('id');
      c.querySelectorAll('audio').forEach(a => { a.removeAttribute('src'); a.preload = 'none'; });
      c.querySelectorAll('.st-secret').forEach(e => e.remove());
      return c;
    });
  }
  function reality(x, y, level = 0) {
    if (!pristine) return;
    if (document.querySelector('.st-real')) { if (!level) return; document.querySelectorAll('.st-real').forEach(n => n.remove()); }
    const win = el('div', 'st-real st-fx'), hole = el('div', 'st-real-hole'), inner = el('div', 'st-real-page');
    win.setAttribute('aria-hidden', 'true');
    inner.append(...pristine.map(n => n.cloneNode(true)));
    inner.style.cssText = `width:${document.documentElement.clientWidth}px;background:${bodyBg};transform:translateY(${-scrollY}px)`;
    hole.append(inner); win.append(hole, el('div', 'st-real-ring'));
    win.style.setProperty('--st-x', x.toFixed(0) + 'px'); win.style.setProperty('--st-y', y.toFixed(0) + 'px');
    document.body.append(win);
    const r = level ? 200 + 75 * level : rand(170, 260), duration = 2600 + 550 * level;
    win.animate([{'--st-r': '0px'}, {'--st-r': r + 'px', offset: .12}, {'--st-r': r * .92 + 'px', offset: .7}, {'--st-r': '0px'}], {duration, easing: 'cubic-bezier(.2,.8,.3,1)'});
    later(() => win.remove(), duration + 50);
  }
  // Throw the page elements near the blast away from it, singe the closest, then let them spring back.
  const BLAST = '.irl-song .st-l, .irl-song .artwork, .irl-song .artist, .irl-song audio, .irl-song .actions > *, .irl-song label, .irl-song input, .irl-song .next a, .irl-song .eyebrow, .site-header a, .site-header .header-location, .site-header button, .site-footer > *';
  function knockback(x, y, k = 1) {
    const radius = Math.max(260, Math.min(innerWidth, innerHeight) * .4) * (1 + .4 * (k - 1));
    for (const n of document.querySelectorAll(BLAST)) {
      const r = n.getBoundingClientRect(); if (!r.width) continue;
      const cx = r.left + r.width / 2, cy = r.top + r.height / 2, d = Math.hypot(cx - x, cy - y);
      if (d > radius) continue;
      const f = 1 - d / radius, a = Math.atan2(cy - y, cx - x) + rand(-.3, .3), push = (30 + 170 * f) * (1 + .35 * (k - 1));
      n.classList.remove('st-back'); n.classList.add('st-hit'); if (f > .45) n.classList.add('st-singed');
      n.style.translate = `${(Math.cos(a) * push).toFixed(0)}px ${(Math.sin(a) * push).toFixed(0)}px`;
      n.style.rotate = `${(rand(-1, 1) * 80 * f).toFixed(0)}deg`;
      later(() => { n.classList.replace('st-hit', 'st-back'); n.style.translate = n.style.rotate = ''; n.classList.remove('st-singed'); later(() => n.classList.remove('st-back'), 1700); }, 220 + 300 * f);
    }
  }
  function concuss(level = 0) {
    flash(Math.min(1, .55 + .15 * level));
    if (level >= 2) { root.classList.add('st-shake'); later(() => root.classList.remove('st-shake'), 300 + 200 * level); } else bump();
    const c = el('div', 'st-concuss st-fx'), ms = 700 + 300 * level; c.style.animationDuration = ms + 'ms';
    document.body.append(c); later(() => c.remove(), ms);
  }

  function enter(next) {
    const prev = stage; stage = next;
    if (next === 'normal') { tripOff(); return; }
    if (!layer) tripOn();
    if (next === 'crash' && prev !== 'crash') crash();
    if (next === 'bombs' && prev !== 'bombs') { say('Side effects may include explosions'); bombLoop(); }
    if (next === 'worlds') say('');
    if (next === 'comedown') { clearTimeout(bombTimer); say('Take two spoonfuls and call me in the morning.'); }
  }
  // The easter egg: the bottle appears during NyQuil; its note starts the secret song, which starts the trip.
  function revealBottle() {
    shown = true;
    bottle = el('button', 'st-secret', BOTTLE); bottle.type = 'button'; bottle.setAttribute('aria-label', 'Secret: open a note from Ian');
    const label = el('span'); label.textContent = secret.caption || 'Click here'; bottle.append(label);
    bottle.addEventListener('click', openNote);
    audio.insertAdjacentElement('afterend', bottle);
    const status = document.getElementById('copy-status'); if (status) status.textContent = 'Something appeared below the player. Select the purple bottle.';
  }
  function openNote() {
    if (!note) {
      note = el('dialog', 'st-note st-fx'); note.setAttribute('aria-labelledby', 'st-note-title');
      const h = el('h2'); h.id = 'st-note-title'; h.textContent = secret.noteTitle || `Secret Song: ${secret.title}`; note.append(h);
      for (const item of Array.isArray(secret.paragraphs) ? secret.paragraphs : []) {
        const para = el('p'); para.textContent = typeof item === 'string' ? item : item?.text || '';
        if (item?.lead) { const lead = el('strong'); lead.textContent = item.lead + ' '; para.prepend(lead); }
        note.append(para);
      }
      const actions = el('div', 'st-note-actions'), play = el('button'), close = el('button');
      play.type = close.type = 'button'; play.textContent = `Play “${secret.title}”`; close.textContent = secret.closeLabel || 'Close';
      play.addEventListener('click', startSecret); close.addEventListener('click', () => note.close());
      note.addEventListener('click', e => { if (e.target === note) note.close(); });
      actions.append(play, close); note.append(actions); document.body.append(note);
    }
    note.showModal();
  }
  function startSecret() {
    note.close(); bottle.hidden = true; mode = 'secret'; stopped = false;
    snapshot(); // reality is the sober NyQuil page, taken before the title changes
    if (targets.title) targets.title.textContent = secret.title;
    audio.src = secret.file; audio.play().catch(() => {});
  }
  function endSecret() {
    mode = 'song'; enter('normal'); root.classList.remove('st-calm');
    if (targets.title) targets.title.textContent = base.title;
    audio.src = base.song; bottle.hidden = false; stopButton?.remove(); stopButton = null;
  }
  function update() {
    if (mode === 'song') {
      if (!shown && !audio.paused && audio.currentTime >= (Number(secret.revealAt) || 10)) revealBottle();
      return;
    }
    if (audio.ended) { endSecret(); return; }
    if (stopped) return;
    const playing = !audio.paused, t = audio.currentTime;
    const next = playing ? stageAt(t, audio.duration) : 'normal';
    if (calm.matches) { root.classList.toggle('st-calm', next !== 'normal'); return; } // reduced motion: calm negative colours only
    if (next !== stage) enter(next);
    setWorld(next === 'crash' ? 'st-negative' : next === 'worlds' || next === 'bombs' ? worldAt(t) : '');
    // Drop each peak's bomb early enough to land and flash before the peak; seeking back re-arms it.
    for (const [at, level] of PEAKS) {
      const lead = 1.8 + .25 * level;
      if (t < at - lead - 1) fired.delete(at);
      else if (layer && !fired.has(at) && t >= at - lead && t < at - .3 && next !== 'comedown') { fired.add(at); dropPeak(at, level); }
    }
  }
  for (const type of ['play', 'playing', 'pause', 'ended', 'seeked', 'timeupdate']) audio.addEventListener(type, update);
})();
