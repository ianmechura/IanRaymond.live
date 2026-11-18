/* WordPress release editor: stage uploads, then publish the complete ordered catalog. */
(() => {
  'use strict';
  const form = document.getElementById('irl-release-form');
  if (!form) return;
  const list = document.getElementById('irl-releases'), editor = document.getElementById('irl-editor');
  const status = document.getElementById('irl-catalog-status');
  let releases = [], revision = '', dirty = false, busy = false;
  const uid = () => crypto.randomUUID();
  const say = (message, error = false) => { status.textContent = message; status.classList.toggle('irl-error', error); };
  const changed = () => { dirty = true; say('Unsaved changes. Click Save releases to publish them.'); };
  function node(tag, className, value) {
    const n = document.createElement(tag); if (className) n.className = className;
    if (value !== undefined) n.textContent = value; return n;
  }
  function button(label, action, className = 'button') {
    const b = node('button', className, label); b.type = 'button'; b.addEventListener('click', action); return b;
  }
  async function api(path, options = {}) {
    const response = await fetch(IRL_ADMIN.api + path, {...options, credentials: 'same-origin', headers: {'X-WP-Nonce': IRL_ADMIN.nonce, ...(options.headers || {})}});
    let data;
    try { data = await response.json(); } catch { throw new Error('The server did not return a valid response. Check your connection and try again.'); }
    if (!response.ok) throw new Error(data.message || `Request failed (${response.status}).`);
    return data;
  }
  function artworkURL(path) {
    if (!path) return '';
    if (path.startsWith('media/')) return IRL_ADMIN.media + path.slice(6).split('/').map(encodeURIComponent).join('/');
    if (path.startsWith('content/')) return IRL_ADMIN.player + path.split('/').map(encodeURIComponent).join('/');
    try { const url = new URL(path); return ['http:', 'https:'].includes(url.protocol) ? url.href : ''; } catch { return ''; }
  }
  function move(array, index, delta) {
    const other = index + delta;
    if (other < 0 || other >= array.length) return;
    [array[index], array[other]] = [array[other], array[index]]; changed(); render();
  }
  function orderButtons(array, index, label) {
    const wrap = node('span', 'irl-order');
    for (const [caption, delta] of [['Move up', -1], ['Move down', 1]]) {
      const b = button(caption, () => move(array, index, delta)); b.setAttribute('aria-label', `${caption}: ${label}`);
      b.disabled = index + delta < 0 || index + delta >= array.length; wrap.append(b);
    }
    return wrap;
  }
  function titleInput(value, label, update) {
    const input = node('input'); input.type = 'text'; input.value = value; input.required = true; input.maxLength = 255;
    input.setAttribute('aria-label', label); input.addEventListener('input', () => { update(input.value); changed(); }); return input;
  }
  function slugField(item, caption) {
    const label = node('label', 'irl-slug-label', caption);
    const input = titleInput(item.slug || '', caption, value => { item.slug = value; });
    input.required = false; input.maxLength = 400; input.pattern = '[a-zA-Z0-9_\\-]+';
    input.placeholder = 'Generated when first saved';
    label.append(input); return label;
  }
  async function upload(files, release, replaceTrack = null, format = 'mp3') {
    if (busy || !files.length) return;
    busy = true; editor.disabled = true; const errors = [];
    try {
      for (const file of files) {
        try {
          if (!file.name.toLowerCase().endsWith('.' + format) || !file.size || file.size > 512 * 1024 * 1024) throw new Error(`Choose a ${format.toUpperCase()} under 512 MB.`);
          const chunkSize = 1024 * 1024, total = Math.ceil(file.size / chunkSize), token = uid(); let result;
          for (let index = 0; index < total; index++) {
            say(`Uploading ${file.name} — ${Math.round(index / total * 100)}%`);
            const body = new FormData();
            for (const [key, value] of Object.entries({name: file.name, token, index, total})) body.append(key, value);
            body.append('chunk', file.slice(index * chunkSize, (index + 1) * chunkSize), file.name);
            result = await api('catalog-upload', {method: 'POST', body});
          }
          if (replaceTrack) { replaceTrack[format === 'wav' ? 'wav' : 'file'] = result.file; if (format === 'mp3') delete replaceTrack.wav; }
          else release.tracks.push({id: 'song-' + uid(), title: result.title || file.name.replace(/\.mp3$/i, ''), file: result.file, artwork: '', story: '', lyrics: '', credits: ''});
          dirty = true;
        } catch (error) { errors.push(`${file.name}: ${error.message}`); }
      }
    } finally {
      busy = false; editor.disabled = false; render();
      say(errors.length ? errors.join('\n') + (dirty ? '\nSuccessful uploads are ready. Save releases to publish them.' : '') : 'Upload complete. Review the titles and order, then Save releases to publish.', !!errors.length);
    }
  }
  function filePicker(release, track = null, format = 'mp3') {
    const input = node('input'); input.type = 'file'; input.accept = format === 'wav' ? '.wav,audio/wav' : '.mp3,audio/mpeg'; input.multiple = !track; input.hidden = true;
    input.addEventListener('change', () => { const files = [...input.files]; input.value = ''; upload(files, release, track, format); });
    const wrap = node('span'); wrap.append(input, button(format === 'wav' ? (track.wav ? 'Replace WAV' : 'Upload WAV') : track ? 'Replace MP3' : 'Upload songs', () => input.click())); return wrap;
  }
  function chooseCover(release) {
    const picker = wp.media({title: 'Choose release artwork', button: {text: 'Use this cover'}, library: {type: 'image'}, multiple: false});
    picker.on('select', () => {
      const image = picker.state().get('selection').first().toJSON();
      release.artworkId = image.id; release.artwork = image.sizes?.large?.url || image.url; changed(); render();
    }); picker.open();
  }
  function render() {
    list.replaceChildren();
    releases.forEach((release, index) => {
      const card = node('section', 'irl-release'); card.dataset.releaseId = release.id;
      const heading = node('h3', '', release.title || 'New release');
      const cover = node('div', 'irl-cover'); const url = artworkURL(release.artwork);
      if (url) { const image = node('img'); image.src = url; image.alt = release.title + ' cover'; cover.append(image); }
      else cover.append(node('div', 'irl-cover-placeholder', 'No cover selected'));
      cover.append(button('Choose artwork', () => chooseCover(release)));
      if (url) cover.append(button('Remove artwork', () => { release.artwork = ''; release.artworkId = 0; changed(); render(); }));
      const fields = node('div', 'irl-release-fields');
      const label = node('label', '', 'Release name'); const name = titleInput(release.title, 'Release name', value => { release.title = value; heading.textContent = value || 'New release'; }); label.append(name);
      const typeLabel = node('label', '', 'Release type'); const type = node('select'); type.setAttribute('aria-label', 'Release type');
      for (const [value, caption] of [['album', 'Album'], ['single', 'Single']]) { const opt = node('option', '', caption); opt.value = value; type.append(opt); }
      type.value = release.type; type.addEventListener('change', () => { release.type = type.value; changed(); }); typeLabel.append(type);
      fields.append(label, slugField(release, 'Release URL slug'), typeLabel);
      fields.append(node('small', '', 'Display names can change without changing links. Editing a URL slug changes the address; old links will stop working.'));
      const top = node('div', 'irl-release-top'); top.append(cover, fields);
      const controls = node('div', 'irl-release-controls');
      controls.append(orderButtons(releases, index, release.title || 'New release'), button('Remove release', () => {
        if (!confirm(`Remove “${release.title || 'this release'}” from the player? Files are kept. The change takes effect when you save.`)) return;
        releases.splice(index, 1); changed(); render();
      }, 'button irl-remove'));
      const songs = node('ol', 'irl-song-list');
      release.tracks.forEach((track, songIndex) => {
        const row = node('li', 'irl-song'); row.dataset.trackId = track.id;
        const name = titleInput(track.title, `Song ${songIndex + 1} title`, value => { track.title = value; });
        const detail = node('small', '', track.file.split('/').pop().replace(/^[a-f0-9-]{36}-/, ''));
        const title = node('div', 'irl-song-title'); const nameLabel = node('label', '', 'Song display name'); nameLabel.append(name);
        title.append(nameLabel, slugField(track, 'Song URL slug'), detail);
        const actions = node('div', 'irl-song-actions'); actions.append(orderButtons(release.tracks, songIndex, track.title), filePicker(release, track), filePicker(release, track, 'wav'), button('Remove', () => {
          if (!confirm(`Remove “${track.title}” from this release? The change takes effect when you save.`)) return;
          release.tracks.splice(songIndex, 1); changed(); render();
        }, 'button irl-remove'));
        row.append(title, actions); songs.append(row);
      });
      card.append(heading, top, controls, node('h4', '', 'Songs'), songs);
      if (!release.tracks.length) card.append(node('p', '', 'Upload the songs for this release before saving.'));
      card.append(filePicker(release)); list.append(card);
    });
  }
  document.getElementById('irl-add-release').addEventListener('click', () => {
    releases.push({id: 'release-' + uid(), type: 'album', title: '', artwork: '', artworkId: 0, tracks: []});
    changed(); render(); list.lastElementChild.querySelector('input[type=text]').focus();
  });
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (busy || !form.reportValidity()) return;
    busy = true; editor.disabled = true; say('Saving releases…');
    try {
      const data = await api('catalog', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({revision, releases})});
      releases = data.catalog.releases; revision = data.revision; dirty = false; render(); say('Saved. Your albums and singles are live on the player.');
    } catch (error) { say(error.message + ' Your edits are still here.', true); }
    finally { busy = false; editor.disabled = false; }
  });
  window.addEventListener('beforeunload', event => { if (dirty || busy) { event.preventDefault(); event.returnValue = ''; } });
  editor.disabled = true;
  api('catalog').then(data => { releases = data.catalog.releases; revision = data.revision; render(); editor.disabled = false; say('Edit your releases below. Changes go live when you save.'); }).catch(error => say('Could not load releases: ' + error.message + ' Reload this page to try again.', true));
})();
