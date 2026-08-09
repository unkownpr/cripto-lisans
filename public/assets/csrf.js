/* CSRF glue — attaches the session token to same-origin unsafe requests.
   Loads before app.js/install.js so the patched fetch is in place first. */
'use strict';
(function () {
  var meta = document.querySelector('meta[name="csrf-token"]');
  var token = meta ? meta.getAttribute('content') : '';
  if (!token) return;

  // 1. Same-origin, state-changing fetch() → add X-CSRF-Token header.
  var origFetch = window.fetch.bind(window);
  window.fetch = function (input, init) {
    init = init || {};
    var url = typeof input === 'string' ? input : (input && input.url) || '';
    var method = (init.method || (typeof input === 'object' && input.method) || 'GET').toUpperCase();
    var sameOrigin = url === '' || url.charAt(0) === '/' || url.indexOf(location.origin) === 0;
    if (sameOrigin && method !== 'GET' && method !== 'HEAD') {
      var headers = new Headers(init.headers || {});
      if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
      init.headers = headers;
    }
    return origFetch(input, init);
  };

  // 2. Every POST <form> → inject a hidden _csrf field (covers programmatic submit).
  function stamp() {
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) {
      var f = forms[i];
      if ((f.getAttribute('method') || 'get').toLowerCase() !== 'post') continue;
      if (f.querySelector('input[name="_csrf"]')) continue;
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_csrf';
      input.value = token;
      f.appendChild(input);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', stamp);
  } else {
    stamp();
  }
})();
