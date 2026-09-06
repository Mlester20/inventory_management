<!DOCTYPE html>

<html
  lang="en"
  class="light-style customizer-hide"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="{{ asset('assets/') }}"
  data-template="vertical-menu-template-free"
>
<head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />
    <title>Reset Password</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/icon.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/auth-custom.css') }}" />
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
</head>
<body class="auth-body">

    <div class="auth-page">
      <div class="auth-blob auth-blob-1"></div>
      <div class="auth-blob auth-blob-2"></div>
      <div class="auth-blob auth-blob-3"></div>

      <div class="auth-split">
        <div class="auth-form-panel">
          <div class="auth-card">
            <div class="auth-card-header">
              <span class="auth-card-logo">
                <img src="{{ asset('assets/img/favicon/icon.png') }}" alt="SAIMS" />
              </span>
              <h2 class="auth-card-title">Reset Password</h2>
              <p class="auth-card-subtitle">
                @if ($step === 'code')
                  Enter the verification code we emailed you
                @else
                  Code verified — set your new password
                @endif
              </p>
            </div>

            @if ($errors->any())
              <div class="auth-alert" role="alert">
                {{ $errors->first() }}
              </div>
            @endif

            @if ($step === 'code')
              <form class="auth-form" action="{{ route('password.verify-code') }}" method="POST">
                @csrf

                <div class="auth-input-group mb-3">
                  <label for="email" class="auth-label">Email</label>
                  <div class="auth-input-wrapper">
                    <i class='bx bx-envelope auth-input-icon' aria-hidden="true"></i>
                    <input
                      type="email"
                      class="form-control auth-input"
                      id="email"
                      name="email"
                      placeholder="Enter your email"
                      value="{{ old('email', $email) }}"
                      autofocus
                      spellcheck="false"
                      autocomplete="username"
                    />
                  </div>
                </div>

                <div class="auth-input-group mb-1">
                  <label for="code" class="auth-label">Verification Code</label>
                  <div class="auth-input-wrapper">
                    <i class='bx bx-hash auth-input-icon' aria-hidden="true"></i>
                    <input
                      type="text"
                      class="form-control auth-input"
                      id="code"
                      name="code"
                      placeholder="6-digit code"
                      value="{{ old('code') }}"
                      inputmode="numeric"
                      pattern="[0-9]*"
                      maxlength="6"
                      autocomplete="one-time-code"
                    />
                  </div>
                </div>

                <div class="auth-form-meta">
                  <form action="{{ route('password.email') }}" method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="email" value="{{ old('email', $email) }}" />
                    <button type="submit" id="resend-code-btn" class="auth-forgot-link auth-resend-btn">
                      Resend code
                    </button>
                  </form>
                </div>

                <button class="auth-submit-btn" type="submit">
                  <span class="auth-submit-label">Verify Code</span>
                  <span class="auth-submit-spinner" aria-hidden="true"></span>
                </button>
              </form>
            @else
              <form class="auth-form" action="{{ route('password.store') }}" method="POST">
                @csrf

                <input type="hidden" name="email" value="{{ $email }}" />
                <input type="hidden" name="code" value="{{ $code }}" />

                <div class="auth-input-group mb-3">
                  <label class="auth-label">Email</label>
                  <div class="auth-input-wrapper">
                    <i class='bx bx-envelope auth-input-icon' aria-hidden="true"></i>
                    <input type="email" class="form-control auth-input" value="{{ $email }}" disabled />
                  </div>
                </div>

                <div class="auth-input-group mb-3">
                  <label for="password" class="auth-label">New Password</label>
                  <div class="auth-input-wrapper">
                    <i class='bx bx-lock-alt auth-input-icon' aria-hidden="true"></i>
                    <input
                      type="password"
                      id="password"
                      class="form-control auth-input"
                      name="password"
                      placeholder="Enter your new password"
                      autofocus
                      autocomplete="new-password"
                    />
                    <button type="button" class="auth-password-toggle" aria-label="Show password" aria-pressed="false">
                      <i class='bx bx-hide' aria-hidden="true"></i>
                    </button>
                  </div>
                </div>

                <div class="auth-input-group mb-1">
                  <label for="password_confirmation" class="auth-label">Confirm Password</label>
                  <div class="auth-input-wrapper">
                    <i class='bx bx-lock-alt auth-input-icon' aria-hidden="true"></i>
                    <input
                      type="password"
                      id="password_confirmation"
                      class="form-control auth-input"
                      name="password_confirmation"
                      placeholder="Confirm your new password"
                      autocomplete="new-password"
                    />
                    <button type="button" class="auth-password-toggle" aria-label="Show password" aria-pressed="false">
                      <i class='bx bx-hide' aria-hidden="true"></i>
                    </button>
                  </div>
                </div>

                <button class="auth-submit-btn" type="submit">
                  <span class="auth-submit-label">Reset Password</span>
                  <span class="auth-submit-spinner" aria-hidden="true"></span>
                </button>
              </form>
            @endif

            <div class="auth-divider"><span>SAIMS</span></div>

            <p class="text-center mb-0">
              <a href="{{ route('auth') }}" class="auth-forgot-link">
                <i class="bx bx-chevron-left"></i> Back to login
              </a>
            </p>
          </div>
        </div>
      </div>
    </div>

    @include('sweetalert::alert')

    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script src="{{ asset('assets/js/auth-custom.js') }}"></script>
    <script>
      (function () {
        var resendBtn = document.getElementById('resend-code-btn');
        if (!resendBtn) return;

        var seconds = {{ \App\Http\Controllers\Auth\PasswordResetLinkController::RESEND_THROTTLE_SECONDS }};
        var defaultLabel = 'Resend code';

        function tick() {
          if (seconds <= 0) {
            resendBtn.disabled = false;
            resendBtn.textContent = defaultLabel;
            return;
          }
          resendBtn.disabled = true;
          resendBtn.textContent = defaultLabel + ' (' + seconds + 's)';
          seconds--;
          setTimeout(tick, 1000);
        }

        tick();
      })();
    </script>
</body>
</html>
