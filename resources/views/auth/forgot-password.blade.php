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
    <title>Forgot Password</title>
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
              <h2 class="auth-card-title">Forgot Password?</h2>
              <p class="auth-card-subtitle">Enter your email and we'll send you a reset link</p>
            </div>

            @if ($errors->any())
              <div class="auth-alert" role="alert">
                {{ $errors->first() }}
              </div>
            @endif

            <form class="auth-form" action="{{ route('password.email') }}" method="POST">
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
                    value="{{ old('email') }}"
                    autofocus
                    spellcheck="false"
                    autocomplete="username"
                  />
                </div>
              </div>

              <button class="auth-submit-btn" type="submit">
                <span class="auth-submit-label">Send Reset Link</span>
                <span class="auth-submit-spinner" aria-hidden="true"></span>
              </button>
            </form>

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
</body>
</html>
