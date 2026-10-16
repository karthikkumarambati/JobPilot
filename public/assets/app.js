document.addEventListener('click', async (event) => {
  const toggle = event.target.closest('[data-toggle]');
  if (toggle) {
    const target = document.getElementById(toggle.dataset.toggle);
    if (target) target.classList.toggle('hidden');
  }
  const copy = event.target.closest('[data-copy]');
  if (copy) {
    const source = document.getElementById(copy.dataset.copy);
    if (!source) return;
    try {
      await navigator.clipboard.writeText(source.value);
      const old = copy.textContent; copy.textContent = 'Copied';
      setTimeout(() => { copy.textContent = old; }, 1200);
    } catch (_) {
      source.classList.remove('hidden'); source.select();
      copy.textContent = 'Select and copy manually';
    }
  }
});
