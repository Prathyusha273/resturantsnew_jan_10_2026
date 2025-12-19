<!doctype html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>

        <meta charset="utf-8">

        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- CSRF Token -->

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title id="app_name"><?php echo @$_COOKIE['meta_title']; ?></title>

        <!-- Fonts -->

        <link rel="icon" type="image/x-icon" href="{{ asset('images/logo-light-icon.png') }}">

        <link rel="dns-prefetch" href="//fonts.gstatic.com">

        <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

        <!-- Styles -->

        <link href="{{ asset('assets/plugins/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">

        <link href="{{ asset('css/style.css') }}" rel="stylesheet">

        <!-- @yield('style') -->

    </head>

    <body>

        <?php if (isset($_COOKIE['store_panel_color'])) { ?>

        <style type="text/css">
            a,
            a:hover,
            a:focus {
                color: <?php echo $_COOKIE['store_panel_color']; ?>;
            }

            .form-group.default-admin {
                padding: 10px;
                font-size: 14px;
                color: #000;
                font-weight: 600;
                border-radius: 10px;
                box-shadow: 0 0px 6px 0px rgba(0, 0, 0, 0.5);
                margin: 20px 10px 10px 10px;
            }

            .form-group.default-admin .crediantials-field {
                position: relative;
                padding-right: 15px;
                text-align: left;
                padding-top: 5px;
                padding-bottom: 5px;
            }

            .form-group.default-admin .crediantials-field>a {
                position: absolute;
                right: 0;
                top: 0;
                bottom: 0;
                margin: auto;
                height: 20px;
            }

            .btn-primary,
            .btn-primary.disabled,
            .btn-primary:hover,
            .btn-primary.disabled:hover {
                background: <?php echo $_COOKIE['store_panel_color']; ?>;
                border: 1px solid<?php echo $_COOKIE['store_panel_color']; ?>;
            }

            [type="checkbox"]:checked+label::before {
                border-right: 2px solid<?php echo $_COOKIE['store_panel_color']; ?>;
                border-bottom: 2px solid<?php echo $_COOKIE['store_panel_color']; ?>;
            }

            .form-material .form-control,
            .form-material .form-control.focus,
            .form-material .form-control:focus {
                background-image: linear-gradient(<?php echo $_COOKIE['store_panel_color']; ?>,
                        <?php echo $_COOKIE['store_panel_color']; ?>), linear-gradient(rgba(120, 130, 140, 0.13), rgba(120, 130, 140, 0.13));
            }

            .btn-primary.active,
            .btn-primary:active,
            .btn-primary:focus,
            .btn-primary.disabled.active,
            .btn-primary.disabled:active,
            .btn-primary.disabled:focus,
            .btn-primary.active.focus,
            .btn-primary.active:focus,
            .btn-primary.active:hover,
            .btn-primary.focus:active,
            .btn-primary:active:focus,
            .btn-primary:active:hover,
            .open>.dropdown-toggle.btn-primary.focus,
            .open>.dropdown-toggle.btn-primary:focus,
            .open>.dropdown-toggle.btn-primary:hover,
            .btn-primary.focus,
            .btn-primary:focus,
            .btn-primary:not(:disabled):not(.disabled).active:focus,
            .btn-primary:not(:disabled):not(.disabled):active:focus,
            .show>.btn-primary.dropdown-toggle:focus {
                background: <?php echo $_COOKIE['store_panel_color']; ?>;
                border-color: <?php echo $_COOKIE['store_panel_color']; ?>;
                box-shadow: 0 0 0 0.2rem<?php echo $_COOKIE['store_panel_color']; ?>;
            }

            .error {
                color: red;
            }
        </style>

        <?php } ?>

        <section id="wrapper">

            <div class="login-register" <?php if (isset($_COOKIE['store_panel_color'])) { ?>
                style="background-color:<?php echo $_COOKIE['store_panel_color']; ?>; <?php } ?>">

                <div class="login-logo text-center py-3" style="margin-top:5%;">

                    <a href="#" style="display: inline-block;background: #fff;padding: 10px;border-radius: 5px;"><img
                            src="{{ asset('images/logo_web.png') }}"> </a>

                </div>
                <div class="login-box card" style="margin-bottom:0%;">

                    <div class="card-body">

                        @if (session('status'))
                            <div class="alert alert-success">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif

                        @if (count($errors) > 0)
                            <div class="alert alert-danger">
                                <ul class="m-0 ps-3">
                                    @foreach ($errors->all() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form class="form-horizontal form-material" name="login" id="login-box" method="POST"
                            action="{{ route('login') }}" autocomplete="off" novalidate>

                            @csrf

                            <div class="box-title m-b-20">{{ __('Login') }}</div>

                            <div class="form-group">

                                <div class="col-xs-12">

                                    <input class="form-control @error('email') is-invalid @enderror"
                                        placeholder="{{ __('Email Address') }}" id="email" type="email" name="email"
                                        value="{{ old('email') }}" required autocomplete="email" autofocus>

                                </div>

                                @error('email')

                                    <span class="invalid-feedback d-block" role="alert">

                                        <strong>{{ $message }}</strong>

                                    </span>

                                @enderror

                            </div>

                            <div class="form-group">

                                <div class="col-xs-12">

                                    <input id="password" placeholder="{{ __('Password') }}" type="password"
                                        class="form-control @error('password') is-invalid @enderror" name="password"
                                        required autocomplete="current-password">

                                </div>

                                @error('password')

                                    <span class="invalid-feedback d-block" role="alert">

                                        <strong>{{ $message }}</strong>

                                    </span>

                                @enderror

                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember"
                                        {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="remember">
                                        {{ __('Remember Me') }}
                                    </label>
                                </div>

                                <div class="forgot-password">
                                    <p class="mb-0">
                                        <a href="{{ url('forgot-password') }}" class="standard-link"
                                            target="_blank">{{ trans('lang.forgot_password') }}?</a>
                                    </p>
                                </div>
                            </div>

                            <div class="form-group text-center m-t-20">

                                <div class="col-xs-12">

                                    <button type="submit" id="login_btn"
                                        class="btn btn-dark btn-lg btn-block text-uppercase waves-effect waves-light btn btn-primary">

                                        {{ __('Login') }}

                                    </button>

                                    <div class="or-line mb-4 ">

                                        <span>OR</span>

                                        <a href="{{ route('register') }}" id="signup_btn"
                                            class="btn btn-dark btn-lg btn-block text-uppercase waves-effect waves-light btn btn-primary">

                                            {{ trans('lang.sign_up') }}

                                        </a>
                                        @if (session('success'))
                                            <div class="alert alert-success mt-2">
                                                {{ session('success') }}
                                            </div>
                                        @endif
                                    </div>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </section>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <!-- Impersonation check script -->
        <script>
        // Impersonation check with URL parameter support
        (function() {
            console.log('🔍 Checking for impersonation...');

            // Get impersonation key from URL
            const urlParams = new URLSearchParams(window.location.search);
            const impersonationKey = urlParams.get('impersonation_key');

            if (!impersonationKey) {
                console.log('ℹ️ No impersonation key found in URL');
                return;
            }

            console.log('🔍 Impersonation key found:', impersonationKey);

            // Check if there's an impersonation session
            fetch('/api/check-impersonation?impersonation_key=' + encodeURIComponent(impersonationKey))
                .then(response => response.json())
                .then(data => {
                    if (data.has_impersonation) {
                        console.log('✅ Impersonation detected!');
                        console.log('Restaurant UID:', data.restaurant_uid);
                        console.log('Restaurant Name:', data.restaurant_name);

                        // Show loading
                        showImpersonationLoading();

                        // Process impersonation
                        fetch('/api/process-impersonation', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                cache_key: data.cache_key
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                console.log('✅ Impersonation successful!');
                                showImpersonationSuccess(result.restaurant_name);

                                // Redirect to dashboard
                                setTimeout(() => {
                                    window.location.href = '/dashboard';
                                }, 2000);
                            } else {
                                console.error('❌ Impersonation failed:', result.message);
                                showImpersonationError(result.message);
                            }
                        })
                        .catch(error => {
                            console.error('❌ Error processing impersonation:', error);
                            showImpersonationError('Error processing impersonation');
                        });
                    } else {
                        console.log('ℹ️ No valid impersonation session found');
                    }
                })
                .catch(error => {
                    console.error('❌ Error checking impersonation:', error);
                });

            function showImpersonationLoading() {
                const loading = document.createElement('div');
                loading.innerHTML = `
                    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; justify-content: center; align-items: center;">
                        <div style="background: white; padding: 30px; border-radius: 10px; text-align: center;">
                            <div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                            <h3>🔐 Admin Impersonation</h3>
                            <p>Processing impersonation...</p>
                        </div>
                    </div>
                    <style>
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                `;
                document.body.appendChild(loading);
            }

            function showImpersonationSuccess(restaurantName) {
                const success = document.createElement('div');
                success.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; z-index: 9999; max-width: 400px;">
                        <strong>✅ Impersonation Successful!</strong><br>
                        You are now logged in as <strong>${restaurantName}</strong>
                    </div>
                `;
                document.body.appendChild(success);

                setTimeout(() => success.remove(), 5000);
            }

            function showImpersonationError(message) {
                const error = document.createElement('div');
                error.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; z-index: 9999; max-width: 400px;">
                        <strong>❌ Impersonation Failed:</strong><br>
                        ${message}
                    </div>
                `;
                document.body.appendChild(error);

                setTimeout(() => error.remove(), 5000);
            }
        })();
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const loginForm = document.getElementById('login-box');
                const loginButton = document.getElementById('login_btn');
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfInput = loginForm ? loginForm.querySelector('input[name="_token"]') : null;
                let isSubmitting = false;

                // Function to refresh CSRF token (returns Promise)
                function refreshCsrfToken() {
                    return fetch('/csrf-token', {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.token) {
                            // Update meta tag
                            if (csrfMeta) {
                                csrfMeta.setAttribute('content', data.token);
                            }
                            // Update form input
                            if (csrfInput) {
                                csrfInput.value = data.token;
                            }
                            console.log('CSRF token refreshed successfully');
                            return data.token;
                        }
                        throw new Error('No token in response');
                    })
                    .catch(error => {
                        console.error('Error refreshing CSRF token:', error);
                        // If refresh fails, try to regenerate session
                        return fetch('/login', {
                            method: 'GET',
                            credentials: 'same-origin',
                        }).then(() => {
                            // Reload page to get fresh token
                            window.location.reload();
                        });
                    });
                }

                // Refresh token every 30 minutes
                setInterval(refreshCsrfToken, 30 * 60 * 1000);

                // Refresh token when tab becomes visible
                document.addEventListener('visibilitychange', function() {
                    if (!document.hidden && !isSubmitting) {
                        refreshCsrfToken();
                    }
                });

                // Refresh token before form submission and wait for it to complete
                if (loginForm && loginButton) {
                    loginForm.addEventListener('submit', function(e) {
                        // Prevent default submission
                        e.preventDefault();

                        // If already submitting, ignore
                        if (isSubmitting) {
                            return false;
                        }

                        isSubmitting = true;
                        loginButton.disabled = true;
                        loginButton.classList.add('disabled');
                        loginButton.innerText = '{{ __('Logging In...') }}';

                        // Refresh token before submitting
                        refreshCsrfToken()
                            .then(() => {
                                // Small delay to ensure token is updated in form
                                setTimeout(function() {
                                    // Now submit the form
                                    loginForm.submit();
                                }, 200);
                            })
                            .catch(() => {
                                // If token refresh fails, still try to submit (might work)
                                setTimeout(function() {
                                    loginForm.submit();
                                }, 500);
                            });

                        return false;
                    });
                }

                // Initial token refresh on page load (after a short delay)
                setTimeout(refreshCsrfToken, 500);
            });
        </script>

    </body>

</html>
