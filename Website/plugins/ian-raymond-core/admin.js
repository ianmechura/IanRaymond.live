document.addEventListener('click', (event) => {
  const button = event.target.closest('.ir-media-button');
  if (!button) return;
  event.preventDefault();
  const frame = wp.media({ title: 'Choose a file', library: { type: button.dataset.kind }, multiple: false, button: { text: 'Use this file' } });
  frame.on('select', () => { document.getElementById(button.dataset.target).value = frame.state().get('selection').first().toJSON().url; });
  frame.open();
});
