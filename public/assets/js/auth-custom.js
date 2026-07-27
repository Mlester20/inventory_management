/**
 * Login page interactions: password visibility toggle, submit-button
 * loading state, and a small ripple effect. Purely presentational —
 * does not touch form action/inputs/validation.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var passwordInput = document.getElementById('password');
    var toggleBtn = document.getElementById('authPasswordToggle');

    if (passwordInput && toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        var isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';
        toggleBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        toggleBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');

        var icon = toggleBtn.querySelector('i');
        if (icon) {
          icon.classList.toggle('bx-hide', !isHidden);
          icon.classList.toggle('bx-show', isHidden);
        }
      });
    }

    var loginForm = document.getElementById('loginForm');
    var submitBtn = document.getElementById('authSubmitBtn');

    if (loginForm && submitBtn) {
      loginForm.addEventListener('submit', function () {
        submitBtn.classList.add('is-loading');
        submitBtn.disabled = true;
      });

      submitBtn.addEventListener('click', function (event) {
        var rect = submitBtn.getBoundingClientRect();
        var ripple = document.createElement('span');
        var size = Math.max(rect.width, rect.height);

        ripple.className = 'auth-ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';

        submitBtn.appendChild(ripple);
        ripple.addEventListener('animationend', function () {
          ripple.remove();
        });
      });
    }
  });
})();
