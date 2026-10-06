(() => {
'use strict';
const cb = document.getElementById('showPass');
  const pw = document.getElementById('password');
  cb?.addEventListener('change', () => {
    pw.type = cb.checked ? 'text' : 'password';
  });
})();
