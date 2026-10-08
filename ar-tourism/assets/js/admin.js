/* Admin: confirmation modal for destructive forms, slug-free helpers. */
(function () {
  'use strict';
  const modalEl = document.getElementById('confirmModal');
  if (!modalEl || !window.bootstrap) return;
  const modal = new window.bootstrap.Modal(modalEl);
  let pendingForm = null;

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
    e.preventDefault();
    pendingForm = form;
    document.getElementById('confirmBody').textContent = form.dataset.confirm;
    modal.show();
  });
  document.getElementById('confirmYes').addEventListener('click', () => {
    if (!pendingForm) return;
    pendingForm.dataset.confirmed = '1';
    modal.hide();
    pendingForm.submit();
  });

  // image preview for file inputs marked data-preview="#imgId"
  document.querySelectorAll('input[type=file][data-preview]').forEach((input) => {
    input.addEventListener('change', () => {
      const img = document.querySelector(input.dataset.preview);
      if (img && input.files[0]) { img.src = URL.createObjectURL(input.files[0]); img.hidden = false; }
    });
  });
})();
