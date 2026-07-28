/**
 * Shared auth-page interactions (login, forgot-password, reset-password):
 * password visibility toggle, submit-button loading state, and a small
 * ripple effect. Purely presentational — does not touch form action,
 * inputs, or validation. Selectors are class-based so any number of
 * password fields / forms on a page are wired up automatically.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.auth-password-toggle').forEach(function (toggleBtn) {
      var wrapper = toggleBtn.closest('.auth-input-wrapper');
      var passwordInput = wrapper ? wrapper.querySelector('input') : null;
      if (!passwordInput) {
        return;
      }

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
    });

    document.querySelectorAll('.auth-submit-btn').forEach(function (submitBtn) {
      var form = submitBtn.closest('form');
      if (form) {
        form.addEventListener('submit', function () {
          submitBtn.classList.add('is-loading');
          submitBtn.disabled = true;
        });
      }

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
    });
  });
})();
