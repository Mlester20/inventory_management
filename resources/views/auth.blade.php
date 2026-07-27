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
    <title>Sign In</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/icon.png" />
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
      <!-- Decorative floating gradient blobs -->
      <div class="auth-blob auth-blob-1"></div>
      <div class="auth-blob auth-blob-2"></div>
      <div class="auth-blob auth-blob-3"></div>

      <div class="auth-split">
        <!-- LEFT: Branding / Illustration (hidden on tablet/mobile) -->
        <div class="auth-illustration-panel">
          <div class="auth-illustration-content">
            <div class="auth-illustration-logo">
              <img src="{{ asset('assets/img/favicon/icon.png') }}" alt="SAIMS" />
              <span>SAIMS</span>
            </div>

            <div class="auth-illustration-art" aria-hidden="true">
              <svg viewBox="0 0 460 320" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                <defs>
                  <linearGradient id="authBarGradient" x1="0" y1="1" x2="0" y2="0">
                    <stop offset="0%" stop-color="#6366f1" />
                    <stop offset="100%" stop-color="#4f46e5" />
                  </linearGradient>
                  <linearGradient id="authBoxGradient" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#4f46e5" />
                    <stop offset="100%" stop-color="#6366f1" />
                  </linearGradient>
                </defs>

                <!-- soft depth glows -->
                <circle cx="400" cy="260" r="70" fill="#4f46e5" opacity="0.12" />
                <circle cx="60" cy="55" r="50" fill="#6366f1" opacity="0.12" />

                <!-- dashboard / analytics card -->
                <rect x="170" y="20" width="260" height="190" rx="20" fill="#ffffff" />
                <circle cx="192" cy="44" r="4" fill="#22c55e" />
                <circle cx="206" cy="44" r="4" fill="#6366f1" />
                <circle cx="220" cy="44" r="4" fill="#e2e8f0" />

                <rect x="200" y="130" width="30" height="50" rx="6" fill="url(#authBarGradient)" />
                <rect x="246" y="100" width="30" height="80" rx="6" fill="url(#authBarGradient)" />
                <rect x="292" y="70" width="30" height="110" rx="6" fill="url(#authBarGradient)" />
                <rect x="338" y="40" width="30" height="140" rx="6" fill="url(#authBarGradient)" />

                <polyline
                  points="215,122 261,92 307,62 353,32"
                  fill="none"
                  stroke="#22c55e"
                  stroke-width="3"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
                <circle cx="215" cy="122" r="4.5" fill="#ffffff" stroke="#22c55e" stroke-width="3" />
                <circle cx="261" cy="92" r="4.5" fill="#ffffff" stroke="#22c55e" stroke-width="3" />
                <circle cx="307" cy="62" r="4.5" fill="#ffffff" stroke="#22c55e" stroke-width="3" />
                <circle cx="353" cy="32" r="4.5" fill="#ffffff" stroke="#22c55e" stroke-width="3" />

                <rect x="352" y="60" width="66" height="24" rx="12" fill="#22c55e" />
                <text x="385" y="76" text-anchor="middle" font-size="12" font-weight="700" fill="#ffffff" font-family="Inter, sans-serif">+24%</text>

                <!-- inventory boxes -->
                <rect x="20" y="190" width="100" height="80" rx="12" fill="#e0e7ff" />
                <rect x="40" y="160" width="100" height="80" rx="12" fill="#c7d2fe" />
                <rect x="60" y="130" width="100" height="80" rx="12" fill="url(#authBoxGradient)" />
                <line x1="110" y1="130" x2="110" y2="210" stroke="#ffffff" stroke-width="5" opacity="0.55" />
                <line x1="60" y1="168" x2="160" y2="168" stroke="#ffffff" stroke-width="5" opacity="0.55" />
                <circle cx="155" cy="138" r="16" fill="#22c55e" stroke="#ffffff" stroke-width="3" />
                <path d="M147 138 L153 144 L165 130" stroke="#ffffff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" />

                <!-- barcode scan strip -->
                <rect x="20" y="230" width="190" height="54" rx="10" fill="#ffffff" stroke="#e2e8f0" stroke-width="1.5" />
                <g stroke="#1e293b" stroke-linecap="round">
                  <line x1="32" y1="242" x2="32" y2="272" stroke-width="3" />
                  <line x1="38" y1="242" x2="38" y2="272" stroke-width="2" />
                  <line x1="43" y1="242" x2="43" y2="272" stroke-width="4" />
                  <line x1="51" y1="242" x2="51" y2="272" stroke-width="2" />
                  <line x1="56" y1="242" x2="56" y2="272" stroke-width="3" />
                  <line x1="63" y1="242" x2="63" y2="272" stroke-width="2" />
                  <line x1="68" y1="242" x2="68" y2="272" stroke-width="4" />
                  <line x1="76" y1="242" x2="76" y2="272" stroke-width="3" />
                  <line x1="82" y1="242" x2="82" y2="272" stroke-width="2" />
                  <line x1="88" y1="242" x2="88" y2="272" stroke-width="3" />
                  <line x1="95" y1="242" x2="95" y2="272" stroke-width="4" />
                  <line x1="102" y1="242" x2="102" y2="272" stroke-width="2" />
                  <line x1="108" y1="242" x2="108" y2="272" stroke-width="3" />
                  <line x1="115" y1="242" x2="115" y2="272" stroke-width="2" />
                  <line x1="121" y1="242" x2="121" y2="272" stroke-width="4" />
                  <line x1="129" y1="242" x2="129" y2="272" stroke-width="3" />
                  <line x1="135" y1="242" x2="135" y2="272" stroke-width="2" />
                  <line x1="141" y1="242" x2="141" y2="272" stroke-width="3" />
                  <line x1="148" y1="242" x2="148" y2="272" stroke-width="4" />
                  <line x1="155" y1="242" x2="155" y2="272" stroke-width="2" />
                  <line x1="161" y1="242" x2="161" y2="272" stroke-width="3" />
                  <line x1="168" y1="242" x2="168" y2="272" stroke-width="2" />
                  <line x1="174" y1="242" x2="174" y2="272" stroke-width="4" />
                  <line x1="182" y1="242" x2="182" y2="272" stroke-width="3" />
                  <line x1="189" y1="242" x2="189" y2="272" stroke-width="2" />
                  <line x1="196" y1="242" x2="196" y2="272" stroke-width="3" />
                </g>
                <rect x="24" y="242" width="182" height="3" rx="1.5" fill="#22c55e" opacity="0.85" class="auth-scan-line" />
              </svg>
            </div>

            <h1 class="auth-illustration-title">Sales &amp; Inventory<br />Management System</h1>
            <p class="auth-illustration-subtitle">
              Manage inventory, monitor sales, and streamline your business operations from one centralized platform.
            </p>
          </div>
        </div>

        <!-- RIGHT: Login Card -->
        <div class="auth-form-panel">
          <div class="auth-card">
            <div class="auth-card-header">
              <span class="auth-card-logo">
                <img src="{{ asset('assets/img/favicon/icon.png') }}" alt="SAIMS" />
              </span>
              <h2 class="auth-card-title">Welcome Back</h2>
              <p class="auth-card-subtitle">Sign in to continue</p>
            </div>

            @if ($errors->any())
              <div class="auth-alert" role="alert">
                {{ $errors->first() }}
              </div>
            @endif

            <form class="auth-form" id="loginForm" action="{{ route('login') }}" method="POST">
              @csrf

              <div class="auth-input-group mb-3">
                <label for="email" class="auth-label">Email</label>
                <div class="auth-input-wrapper">
                  <i class='bx bx-envelope auth-input-icon' aria-hidden="true"></i>
                  <input
                    type="text"
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

              <div class="auth-input-group mb-1">
                <label for="password" class="auth-label">Password</label>
                <div class="auth-input-wrapper">
                  <i class='bx bx-lock-alt auth-input-icon' aria-hidden="true"></i>
                  <input
                    type="password"
                    id="password"
                    class="form-control auth-input"
                    name="password"
                    placeholder="Enter your password"
                    aria-describedby="password"
                    autocomplete="current-password"
                  />
                  <button
                    type="button"
                    class="auth-password-toggle"
                    id="authPasswordToggle"
                    aria-label="Show password"
                    aria-pressed="false"
                  >
                    <i class='bx bx-hide' aria-hidden="true"></i>
                  </button>
                </div>
              </div>

              <div class="auth-form-meta">
                <div class="form-check auth-remember">
                  <input class="form-check-input" type="checkbox" id="remember-me" />
                  <label class="form-check-label" for="remember-me">Remember Me</label>
                </div>
                <a href="#" class="auth-forgot-link">Forgot Password?</a>
              </div>

              <button class="auth-submit-btn" type="submit" id="authSubmitBtn">
                <span class="auth-submit-label">Sign In</span>
                <span class="auth-submit-spinner" aria-hidden="true"></span>
              </button>
            </form>

            <div class="auth-divider"><span>SAIMS</span></div>

            <p class="auth-footer-note">Version 1.0 &middot; &copy; {{ date('Y') }} SAIMS</p>
          </div>
        </div>
      </div>
    </div>

    @include('sweetalert::alert')

    <!-- / Content -->
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script src="{{ asset('assets/js/auth-custom.js') }}"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>
</html>
