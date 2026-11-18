(() => {
  const input = document.getElementById('stream-link');
  document.getElementById('copy-link').addEventListener('click', async () => {
    const status = document.getElementById('copy-status');
    try {
      await navigator.clipboard.writeText(input.value);
      status.textContent = 'Streaming link copied.';
    } catch {
      input.focus(); input.select();
      status.textContent = 'Select Copy to copy the highlighted streaming link.';
    }
  });
})();
