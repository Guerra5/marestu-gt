(() => {
  'use strict';

  document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
  });

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-confirm]:not(form), [data-print]');
    if (!button) return;
    if (button.hasAttribute('data-print')) window.print();
    if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) event.preventDefault();
  });
})();
