/* Listener signup modal. Opens 20 seconds after page load unless this browser already signed up or chose
   "Don't tell me about this again". Never touches page audio. */
(() => {
  const cfg = window.IRN_SIGNUP;
  const KEY = 'irn-signup-done';
  if (!cfg || document.getElementById('irn-backdrop')) return;
  const stored = () => { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } };
  const remember = () => { try { localStorage.setItem(KEY, '1'); } catch (e) { /* private mode: shows again next visit */ } };
  const publicSignup = document.getElementById('irn-public-signup');
  if (publicSignup) {
    const form = publicSignup.querySelector('form');
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      const button = form.querySelector('button');
      const error = form.querySelector('.irn-error');
      error.textContent = '';
      button.disabled = true;
      try {
        const response = await fetch(cfg.api, {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'omit',
          body: JSON.stringify({ channel: 'sms', contact: form.elements.contact.value.trim(), sms_consent: form.elements.sms_consent.checked, website: form.elements.website.value, source: location.href }),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.message || 'Something went wrong. Please try again.');
        remember();
        form.replaceWith(Object.assign(document.createElement('p'), { className: 'irn-thanks', textContent: result.message || "Thanks! You're on the list." }));
        publicSignup.querySelector('.irn-thanks').setAttribute('role', 'status');
      } catch (err) {
        error.textContent = err.message;
        button.disabled = false;
      }
    });
    return;
  }
  if (stored()) return;

  const el = (tag, attrs = {}, ...kids) => {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (k === 'text') n.textContent = v; else if (v === true) n.setAttribute(k, ''); else if (v !== false && v != null) n.setAttribute(k, v);
    }
    kids.forEach(k => k && n.append(k));
    return n;
  };
  const link = (href, text) => href ? el('a', { href, target: '_blank', rel: 'noopener', text }) : null;

  function build() {
    const close = el('button', { type: 'button', class: 'irn-close', 'aria-label': 'Close' }, '×');
    const email = el('input', { type: 'email', name: 'contact', autocomplete: 'email', inputmode: 'email', placeholder: 'you@example.com', 'aria-label': 'Email address' });
    const phone = el('input', { type: 'tel', name: 'contact', autocomplete: 'tel-national', inputmode: 'tel', placeholder: '(214) 555-0123', 'aria-label': 'US mobile number', hidden: true, disabled: true });
    const consent = el('input', { type: 'checkbox', name: 'sms_consent', value: '1' });
    const disclosure = el('label', { class: 'irn-consent', hidden: true }, consent,
      el('span', {}, cfg.consent + ' ', link(cfg.termsUrl, 'Terms'), cfg.termsUrl && cfg.privacyUrl ? ' · ' : '', link(cfg.privacyUrl, 'Privacy')));
    const emailNote = el('p', { class: 'irn-note', text: 'One email when there’s new music or a new show. Unsubscribe anytime.' });
    const choice = (value, label, checked) => el('label', { class: 'irn-choice' }, el('input', { type: 'radio', name: 'channel', value, checked }), el('span', { text: label }));
    const error = el('p', { class: 'irn-error', role: 'alert' });
    const submit = el('button', { type: 'submit', class: 'irn-submit', text: 'Sign me up' });
    const trap = el('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', class: 'irn-trap', 'aria-hidden': 'true' });
    const form = el('form', { novalidate: true },
      el('fieldset', { class: 'irn-channels' }, el('legend', { class: 'irn-sr', text: 'How should I reach you?' }), choice('email', 'Email', true), choice('sms', 'Text message', false)),
      email, phone, emailNote, disclosure, trap, error, submit);
    const never = el('input', { type: 'checkbox', id: 'irn-never' });
    const title = el('h2', { id: 'irn-title', text: 'Sign up to be the first to know when new music or live shows are added.' });
    const modal = el('div', { class: 'irn-modal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'irn-title', tabindex: '-1' },
      close, title, form, el('label', { class: 'irn-never', for: 'irn-never' }, never, el('span', { text: 'Don\'t tell me about this again.' })));
    const backdrop = el('div', { id: 'irn-backdrop', class: 'irn-backdrop' }, modal);
    return { backdrop, modal, form, close, email, phone, consent, disclosure, emailNote, error, submit, never };
  }

  function open() {
    if (stored()) return;
    const ui = build();
    const previous = document.activeElement;
    const rootOverflow = document.documentElement.style.overflow;
    let timer = 0;
    const channel = () => ui.form.querySelector('input[name=channel]:checked').value;
    const contact = () => channel() === 'sms' ? ui.phone : ui.email;

    function dismiss() {
      if (ui.never.checked) remember();
      clearTimeout(timer);
      document.removeEventListener('keydown', onKey, true);
      ui.backdrop.remove();
      document.documentElement.style.overflow = rootOverflow;
      if (previous && previous.focus) previous.focus({ preventScroll: true });
    }
    function focusables() {
      return [...ui.modal.querySelectorAll('button, input, a[href]')].filter(n => !n.disabled && !n.hidden && !n.closest('[hidden]') && n.tabIndex >= 0);
    }
    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); dismiss(); return; }
      if (e.key !== 'Tab') return;
      const f = focusables(), first = f[0], last = f[f.length - 1];
      if (!ui.modal.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
      else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
    function showChannel() {
      const sms = channel() === 'sms';
      ui.phone.hidden = ui.phone.disabled = !sms;
      ui.email.hidden = ui.email.disabled = sms;
      ui.disclosure.hidden = !sms;
      ui.emailNote.hidden = sms;
      ui.error.textContent = '';
    }

    ui.close.addEventListener('click', dismiss);
    ui.backdrop.addEventListener('click', e => { if (e.target === ui.backdrop) dismiss(); });
    ui.form.addEventListener('change', e => { if (e.target.name === 'channel') { showChannel(); contact().focus(); } });
    ui.form.addEventListener('submit', async e => {
      e.preventDefault();
      const value = contact().value.trim();
      ui.error.textContent = '';
      if (channel() === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) { ui.error.textContent = 'Enter a valid email address.'; ui.email.focus(); return; }
      if (channel() === 'sms') {
        const digits = value.replace(/\D/g, '').replace(/^1(?=\d{10}$)/, '');
        if (!/^[2-9]\d{2}[2-9]\d{6}$/.test(digits)) { ui.error.textContent = 'Enter a US mobile number, like (214) 555-0123.'; ui.phone.focus(); return; }
        if (!ui.consent.checked) { ui.error.textContent = 'Check the box to agree to receive texts.'; ui.consent.focus(); return; }
      }
      ui.submit.disabled = true; ui.submit.textContent = 'Signing you up…';
      try {
        const r = await fetch(cfg.api, {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'omit',
          body: JSON.stringify({ channel: channel(), contact: value, sms_consent: ui.consent.checked, website: ui.form.website.value, source: location.href }),
        });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(j.message || 'Something went wrong. Please try again.');
        remember();
        ui.form.replaceWith(el('p', { class: 'irn-thanks', role: 'status', text: j.message || 'Thanks! You’re on the list.' }));
        ui.modal.querySelector('.irn-never').remove();
        ui.close.focus();
        timer = setTimeout(dismiss, 4000);
      } catch (err) {
        ui.error.textContent = err.message;
        ui.submit.disabled = false; ui.submit.textContent = 'Sign me up';
      }
    });

    document.addEventListener('keydown', onKey, true);
    document.body.append(ui.backdrop);
    document.documentElement.style.overflow = 'hidden';
    showChannel();
    ui.modal.focus({ preventScroll: true });
  }

  const start = () => setTimeout(open, cfg.delay || 20000);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
