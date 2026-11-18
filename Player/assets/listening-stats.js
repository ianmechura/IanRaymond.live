/* Optional, consent-based play-start reports. Nothing here is required for playback. */
(() => {
  'use strict';
  window.createListeningReports = album => {
    const panel = document.getElementById('listeningPrivacy');
    const status = document.getElementById('listeningStatus');
    const allow = document.getElementById('allowReports');
    const decline = document.getElementById('declineReports');
    const seen = new WeakSet(), queue = new Map();
    let config, prefix, choice = 'unknown', storage = null, timer, controller, busy = false, retry = 60000;
    const MAX = 1000, AGE = 90 * 86400000;
    function paint() {
      if (!config) return;
      panel.hidden = false;
      document.getElementById('listeningDisclosure').textContent = `Help Ian see which songs connect. If you allow reports, this player sends USB ${config.driveId}, the song, album edition and play time to ${new URL(config.endpoint).hostname}. No name or email is requested. Listening works either way.`;
      status.textContent = choice === 'yes' ? 'Listening reports are on. Offline reports wait for a connection while this player is open.' : choice === 'no' ? 'Listening reports are off.' : 'Reports are off until you choose Allow reports.';
      if (!storage) status.textContent += ' This browser cannot save this setting or unsent reports after you close the page.';
      allow.hidden = choice === 'yes';
      decline.textContent = choice === 'yes' ? 'Turn reports off' : 'No thanks';
      decline.hidden = choice === 'no';
    }
    function readChoice() {
      if (storage) {
        try { const value = storage.getItem(prefix + 'choice'); choice = ['yes', 'no'].includes(value) ? value : 'unknown'; }
        catch { storage = null; paint(); }
      }
      return choice;
    }
    function remove(id) {
      queue.delete(id);
      try { storage?.removeItem(prefix + 'event:' + id); } catch { storage = null; paint(); }
    }
    function collect() {
      if (storage) {
        try {
          for (let i = 0; i < storage.length; i++) {
            const key = storage.key(i);
            if (!key?.startsWith(prefix + 'event:')) continue;
            try {
              const event = JSON.parse(storage.getItem(key));
              if (event && key === prefix + 'event:' + event.event_id && Number.isFinite(Date.parse(event.started_at))) queue.set(event.event_id, event);
            } catch { /* Ignore damaged browser storage. */ }
          }
        } catch { storage = null; paint(); }
      }
      const sorted = [...queue.values()].sort((a, b) => a.started_at.localeCompare(b.started_at));
      sorted.forEach((event, i) => { if (Date.parse(event.started_at) < Date.now() - AGE || i < sorted.length - MAX) remove(event.event_id); });
      return [...queue.values()].slice(0, 20);
    }
    function clearQueue() { collect(); [...queue.keys()].forEach(remove); }
    function schedule(delay = retry) { clearTimeout(timer); if (choice === 'yes') timer = setTimeout(flush, delay); }
    async function flush() {
      if (!config || busy) return;
      if (readChoice() !== 'yes') { clearQueue(); paint(); return; }
      const batch = collect();
      if (!batch.length) { schedule(); return; }
      if (navigator.onLine === false) { schedule(); return; }
      busy = true; controller = new AbortController();
      const timeout = setTimeout(() => controller?.abort(), 8000);
      try {
        const response = await fetch(config.endpoint, {
          method: 'POST', mode: 'cors', credentials: 'omit', cache: 'no-store', redirect: 'error',
          headers: {'Content-Type': 'application/json'}, signal: controller.signal,
          body: JSON.stringify({schema: 1, drive_id: config.driveId, token: config.token, events: batch})
        });
        if (!response.ok) throw new Error('Collector unavailable');
        const result = await response.json();
        if (!Array.isArray(result.accepted)) throw new Error('Missing acknowledgement');
        const sent = new Set(batch.map(event => event.event_id));
        result.accepted.forEach(id => { if (sent.has(id)) remove(id); });
        retry = 60000;
        schedule(result.accepted.some(id => sent.has(id)) && queue.size ? 1000 : retry);
      } catch { retry = Math.min(retry * 2, 300000); schedule(); }
      finally { clearTimeout(timeout); controller = null; busy = false; }
    }
    function setChoice(value) {
      choice = value;
      try { storage?.setItem(prefix + 'choice', value); } catch { storage = null; }
      if (value === 'no') { clearTimeout(timer); controller?.abort(); clearQueue(); }
      paint(); if (value === 'yes') flush();
    }
    function report(a, track) {
      if (seen.has(a)) return;
      seen.add(a); // Opting in mid-song does not report an earlier, unconsented start.
      if (!config || readChoice() !== 'yes') return;
      try {
        const event = {
          event_id: crypto.randomUUID(), track_id: String(track.id || '').slice(0, 96),
          title: String(track.title || '').slice(0, 200), edition: String(album.edition || album.title || '').slice(0, 120),
          started_at: new Date().toISOString()
        };
        if (!/^[a-zA-Z0-9_-]{1,96}$/.test(event.track_id)) return;
        queue.set(event.event_id, event);
        try { storage?.setItem(prefix + 'event:' + event.event_id, JSON.stringify(event)); } catch { storage = null; paint(); }
        collect(); flush();
      } catch { /* Reporting must never interrupt music, including older browsers without UUID support. */ }
    }
    const script = document.createElement('script');
    script.src = new URL('drive.js?v=' + Date.now(), location.href).href;
    script.onload = () => {
      try {
        const value = window.IAN_DRIVE, url = new URL(value?.endpoint);
        if (value?.enabled !== true || !/^[1-9][0-9]{0,8}$/.test(String(value.driveId)) || !/^[a-f0-9]{64}$/.test(value.token) || url.protocol !== 'https:' || url.username || url.password || url.hash) return;
        config = {...value, driveId: String(value.driveId)};
        prefix = 'ian-reports:v1:' + config.endpoint + ':' + config.driveId + ':';
        try { storage = window.localStorage; const key = prefix + 'probe'; storage.setItem(key, '1'); storage.removeItem(key); } catch { storage = null; }
        readChoice(); allow.addEventListener('click', () => setChoice('yes')); decline.addEventListener('click', () => setChoice('no'));
        paint(); if (choice === 'yes') flush(); else clearQueue();
      } catch { /* Missing or invalid drive configuration leaves reporting disabled. */ }
    };
    script.onerror = () => {}; document.head.append(script);
    window.addEventListener('online', flush);
    window.addEventListener('storage', event => {
      if (config && event.key === prefix + 'choice') {
        readChoice(); paint();
        if (choice !== 'yes') { clearTimeout(timer); controller?.abort(); clearQueue(); } else flush();
      }
    });
    window.addEventListener('pagehide', () => { clearTimeout(timer); controller?.abort(); });
    window.addEventListener('pageshow', () => { if (config && readChoice() === 'yes') flush(); });
    return {
      watch(a, track) { a.addEventListener('playing', () => report(a, track)); },
      restart(a) { seen.delete(a); }
    };
  };
})();
