@php
    $currentLocale = str_replace('_', '-', app()->getLocale());
    $isRtl = $currentLocale === 'ar' || session('is_rtl') === true;
    $layoutBranding = $layoutBranding ?? [];
    $layoutVendor = $layoutVendor ?? null;
    $layoutUser = $layoutUser ?? auth()->user();

    $branding = $layoutBranding;
    $appName = $branding['applicationName'] ?? config('app.name', 'Restaurant Management System');
    $logoUrl = $branding['appLogo'] ?? asset('/images/logo_web.png');
    $faviconUrl = $branding['favicon'] ?? asset('images/logo-light-icon.png');
    $themeColor = $branding['store_panel_color'] ?? '#ff683a';
@endphp
<!doctype html>
<html lang="{{ $currentLocale }}" @if($isRtl) dir="rtl" @endif>

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title id="app_name">{{ $appName }}</title>
        <link rel="icon" id="favicon" type="image/x-icon" href="{{ $faviconUrl }}">
        <!-- Fonts -->
        <link rel="dns-prefetch" href="//fonts.gstatic.com">
        <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
        <!-- Styles -->
        <link href="{{ asset('assets/plugins/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
        <link rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/css/bootstrap-timepicker.min.css">
        @if($isRtl)
            <link href="{{ asset('assets/plugins/bootstrap/css/bootstrap-rtl.min.css') }}" rel="stylesheet">
        @endif
        <link href="{{ asset('css/style.css') }}" rel="stylesheet">
        @if($isRtl)
            <link href="{{ asset('css/style_rtl.css') }}" rel="stylesheet">
        @endif
        <link href="{{ asset('css/icons/font-awesome/css/font-awesome.css') }}" rel="stylesheet">

        <!-- Preload only critical fonts that are used immediately -->
        <link href="{{ asset('assets/plugins/toast-master/css/jquery.toast.css') }}" rel="stylesheet">
        <link href="{{ asset('css/colors/blue.css') }}" rel="stylesheet">
        <link href="{{ asset('css/chosen.css') }}" rel="stylesheet">
        <link href="{{ asset('css/bootstrap-tagsinput.css') }}" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css">
        <link rel="stylesheet" type="text/css"
            href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">
        <link href="{{ asset('assets/plugins/select2/dist/css/select2.min.css')}}" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" type="text/css"
            href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

        <!--  @yield('style')-->

        <style>
            :root {
                --theme-color: {{ $themeColor }};
            }

            .topbar,
            .btn-primary,
            .btn-primary:hover,
            .btn-primary:focus,
            .btn-warning,
            .btn-warning:hover,
            .btn-warning:focus {
                background-color: var(--theme-color);
                border-color: var(--theme-color);
            }

            a,
            a:hover,
            a:focus,
            .sidebar-nav ul li a:hover,
            .sidebar-nav>ul>li.active>a,
            .sidebar-nav>ul>li.active>a i,
            .order-status .data i,
            .order-status span.count,
            .text-warning,
            .text-info {
                color: var(--theme-color);
            }

            blockquote {
                border-left: 5px solid var(--theme-color);
            }

            .language-options select option,
            .pagination>li>a.page-link:hover,
            .nav-tabs.card-header-tabs .nav-link.active {
                background-color: var(--theme-color);
                color: #fff;
            }
        </style>

        @php
            $vendorIdentifier = optional($layoutVendor)->id ?? ($layoutUser?->getvendorId());
            $currentUserId = $layoutUser->id ?? optional(auth()->user())->id;
        @endphp
        <script type="text/javascript">
            var cuser_id = "{{ $vendorIdentifier }}";
        </script>

    </head>

    <body>

        <div id="app" class="fix-header fix-sidebar card-no-border">
            <div id="main-wrapper">
                <div id="data-table_processing" class="page-overlay" style="display:none;">
                    <div class="overlay-text">
                        <img src="{{asset('images/spinner.gif')}}">
                    </div>
                </div>
                <header class="topbar">
                    <nav class="navbar top-navbar navbar-expand-md navbar-light">
                        @include('layouts.header')
                    </nav>
                </header>
                <aside class="left-sidebar">
                    <!-- Sidebar scroll-->
                    <div class="scroll-sidebar">
                        @include('layouts.menu')
                    </div>
                    <!-- End Sidebar scroll-->
                </aside>
            </div>

            <main class="py-4">
                @yield('content')
            </main>

            <!-- Impersonation Banner -->
            <script>
            // Check if user is impersonated and show notification
            (function() {
                // Only check for impersonation if we have proper session data
                const impersonationData = localStorage.getItem('restaurant_impersonation');
                const currentUser = @json(auth()->user());

                // Validate that we actually have impersonation data and it's not stale
                if (impersonationData && currentUser) {
                    try {
                        const data = JSON.parse(impersonationData);

                        // Additional validation: check if impersonation data is recent (within 24 hours)
                        const impersonatedAt = new Date(data.impersonatedAt);
                        const now = new Date();
                        const hoursDiff = (now - impersonatedAt) / (1000 * 60 * 60);

                        // Only show banner if:
                        // 1. isImpersonated is true
                        // 2. impersonatedAt is valid
                        // 3. impersonation is recent (within 24 hours)
                        // 4. we have valid admin data
                        if (data.isImpersonated &&
                            data.impersonatedAt &&
                            hoursDiff < 24 &&
                            data.admin_id &&
                            data.admin_email) {

                            // Show impersonation banner with improved UI
                            const banner = document.createElement('div');
                            banner.id = 'impersonation-banner';
                            banner.innerHTML = `
                                <div class="alert alert-warning alert-dismissible fade show impersonation-alert" role="alert" style="margin: 0; border-radius: 0; border-left: none; border-right: none;">
                                    <div class="container-fluid">
                                        <div class="row align-items-center">
                                            <div class="col-md-1 text-center">
                                                <i class="fas fa-user-secret fa-2x text-warning"></i>
                                            </div>
                                            <div class="col-md-9">
                                                <h6 class="alert-heading mb-1">
                                                    <i class="fas fa-shield-alt"></i> Admin Impersonation Active
                                                </h6>
                                                <p class="mb-1">You are currently logged in as this restaurant owner for support purposes.</p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i> Impersonated at: ${impersonatedAt.toLocaleString()}
                                                    <span class="mx-2">|</span>
                                                    <i class="fas fa-user-tie"></i> Admin: ${data.admin_email}
                                                </small>
                                            </div>
                                            <div class="col-md-2 text-right">
                                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="endImpersonation()">
                                                    <i class="fas fa-sign-out-alt"></i> End Session
                                                </button>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;

                            // Insert at the very top of the page
                            const body = document.body;
                            if (body.firstChild) {
                                body.insertBefore(banner, body.firstChild);
                            } else {
                                body.appendChild(banner);
                            }
                        } else {
                            // Clean up stale impersonation data
                            localStorage.removeItem('restaurant_impersonation');
                        }
                    } catch (error) {
                        console.error('Error parsing impersonation data:', error);
                        // Clean up corrupted data
                        localStorage.removeItem('restaurant_impersonation');
                    }
                }
            })();

            // Function to end impersonation session
            function endImpersonation() {
                if (confirm('Are you sure you want to end the impersonation session?')) {
                    // Clear impersonation data
                    localStorage.removeItem('restaurant_impersonation');

                    // Remove banner
                    const banner = document.getElementById('impersonation-banner');
                    if (banner) {
                        banner.remove();
                    }

                    // Redirect to logout or admin panel
                    window.location.href = '/logout';
                }
            }

            // Utility function to clear stale impersonation data
            function clearStaleImpersonationData() {
                const impersonationData = localStorage.getItem('restaurant_impersonation');
                if (impersonationData) {
                    try {
                        const data = JSON.parse(impersonationData);
                        const impersonatedAt = new Date(data.impersonatedAt);
                        const now = new Date();
                        const hoursDiff = (now - impersonatedAt) / (1000 * 60 * 60);

                        // Clear data if older than 24 hours or missing required fields
                        if (hoursDiff >= 24 || !data.admin_id || !data.admin_email) {
                            localStorage.removeItem('restaurant_impersonation');
                            console.log('Cleared stale impersonation data');
                        }
                    } catch (error) {
                        localStorage.removeItem('restaurant_impersonation');
                        console.log('Cleared corrupted impersonation data');
                    }
                }
            }

            // Clear stale data on page load
            clearStaleImpersonationData();
            </script>

            <!-- Impersonation Banner Styles -->
            <style>
            .impersonation-alert {
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                border: none;
                border-bottom: 3px solid #ffc107;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                animation: slideDown 0.3s ease-out;
            }

            .impersonation-alert .alert-heading {
                color: #856404;
                font-weight: 600;
            }

            .impersonation-alert p {
                color: #856404;
                margin-bottom: 0.5rem;
            }

            .impersonation-alert small {
                color: #6c757d;
            }

            .impersonation-alert .btn-outline-warning {
                border-color: #ffc107;
                color: #856404;
                font-weight: 500;
            }

            .impersonation-alert .btn-outline-warning:hover {
                background-color: #ffc107;
                border-color: #ffc107;
                color: #212529;
            }

            .impersonation-alert .btn-close {
                background: none;
                border: none;
                font-size: 1.2rem;
                color: #856404;
                opacity: 0.7;
            }

            .impersonation-alert .btn-close:hover {
                opacity: 1;
            }

            @keyframes slideDown {
                from {
                    transform: translateY(-100%);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            /* Responsive adjustments */
            @media (max-width: 768px) {
                .impersonation-alert .col-md-1 {
                    margin-bottom: 1rem;
                }

                .impersonation-alert .col-md-2 {
                    text-align: center !important;
                    margin-top: 1rem;
                }

                .impersonation-alert .btn {
                    margin: 0.25rem;
                }
            }
            </style>

            <div class="modal fade" id="notification_add_order" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered notification-main" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title order_placed_subject" id="exampleModalLongTitle"></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <h6><span id="auth_accept_name" class="order_placed_msg"></span></h6>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary"><a href="{{ url('orders') }}"
                                    id="notification_add_order_a">Go</a></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="notification_rejected_order" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered notification-main" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLongTitle">Order Rejected</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <h6>There have new order rejected.</h6>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary"><a href="{{ url('orders') }}">Go</a></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="notification_accepted_order" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered notification-main" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title driver_accepted_subject" id="exampleModalLongTitle"></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <h6><span id="np_accept_name" class="driver_accepted_msg"></span></h6>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary"><a href="{{ url('orders') }}"
                                    id="notification_accepted_a">Go</a></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="notification_completed_order" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered notification-main" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLongTitle">Order Completed</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <h6>Order has been order accepted.</h6>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary"><a href="{{ url('orders') }}">Go</a></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="notification_book_table_add_order" tabindex="-1" role="dialog"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered notification-main" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title dinein_order_placed_subject" id="exampleModalLongTitle"></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <h6><span id="auth_accept_name_book_table" class="dinein_order_placed_msg"></span></h6>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary"><a href="{{ url('booktable') }}"
                                    id="notification_book_table_add_order_a">Go</a>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="{{ asset('assets/plugins/jquery/jquery.min.js') }}"></script>
        <script src="{{ asset('assets/plugins/bootstrap/js/popper.min.js') }}"></script>
        <script src="{{ asset('assets/plugins/bootstrap/js/bootstrap.min.js') }}"></script>
        <script src="{{ asset('js/jquery.slimscroll.js') }}"></script>
        <script src="{{ asset('js/waves.js') }}"></script>
        <script src="{{ asset('js/sidebarmenu.js') }}"></script>
        <script src="{{ asset('assets/plugins/sticky-kit-master/dist/sticky-kit.min.js') }}"></script>
        <script src="{{ asset('assets/plugins/sparkline/jquery.sparkline.min.js') }}"></script>
        <script src="{{ asset('js/custom.min.js') }}"></script>
        <script src="{{ asset('js/jquery.resizeImg.js') }}"></script>
        <script src="{{ asset('assets/plugins/select2/dist/js/select2.min.js') }}"></script>

        <script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript"
            src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js">
            </script>
        <script type="text/javascript"
            src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/js/bootstrap-timepicker.min.js"></script>
        <script type="text/javascript">
            jQuery(window).scroll(function() {
                var scroll=jQuery(window).scrollTop();
                if(scroll<=60) {
                    jQuery("body").removeClass("sticky");
                } else {
                    jQuery("body").addClass("sticky");
                }
            });
        </script>
        <script src="{{ asset('js/jquery.validate.js') }}"></script>
        <script src="{{ asset('js/chosen.jquery.js') }}"></script>
        <script src="{{ asset('js/bootstrap-tagsinput.js') }}"></script>
        <script type="text/javascript" charset="utf8"
            src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
        <script type="text/javascript" charset="utf8"
            src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script type="text/javascript" charset="utf8"
            src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.1/xlsx.full.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script
            src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.24/jspdf.plugin.autotable.min.js"></script>
        <script type="text/javascript" charset="utf8"
            src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
        <script type="text/javascript" charset="utf8"
            src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
        <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
        <script type="text/javascript"
            src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

        @yield('scripts')
        <script>
            (function () {
                let lastOrderId = "";
                let ringtoneAudio = null;
                let soundEnabled = true; // for future toggle button support

                const POLL = 2000;
                const vendorID = "{{ $vendorIdentifier }}"; // provided from backend
                const ENDPOINT_LATEST = `/orders/latest-id/vendor/${vendorID}`;
                const ENDPOINT_ORDER = (id) => `/orders/get/${id}`;
                const ENDPOINT_RINGTONE = "/settings/ringtone";

                const FALLBACK_BEEP =
                    "data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT";

                // Load ringtone from settings table (if offline → fallback beep)
                function loadRingtone() {
                    $.get(ENDPOINT_RINGTONE, function (res) {
                        try {
                            if (res?.ringtone) {
                                ringtoneAudio = new Audio(res.ringtone);
                                ringtoneAudio.volume = 1.0;
                                console.log("🔔 Ringtone loaded:", res.ringtone);
                            } else {
                                ringtoneAudio = new Audio(FALLBACK_BEEP);
                                ringtoneAudio.volume = 0.3;
                            }
                        } catch (e) {
                            ringtoneAudio = new Audio(FALLBACK_BEEP);
                            ringtoneAudio.volume = 0.3;
                        }
                    }).fail(() => {
                        ringtoneAudio = new Audio(FALLBACK_BEEP);
                        ringtoneAudio.volume = 0.3;
                    });
                }

                // Play sound once
                function playNotificationSound() {
                    if (!soundEnabled) return;
                    try {
                        if (!ringtoneAudio) {
                            ringtoneAudio = new Audio(FALLBACK_BEEP);
                            ringtoneAudio.volume = 0.3;
                        }
                        ringtoneAudio.currentTime = 0;
                        ringtoneAudio.play().catch(() => {});

                        // ⏳ stop after 5 seconds
                        setTimeout(() => {
                            try { ringtoneAudio.pause(); } catch(e) {}
                        }, 10000);

                    } catch (e) {
                        console.error("playNotificationSound error:", e);
                    }
                }

                // Show popup when new order detected
                function showOrderPopup(order) {
                    if (order.scheduleTime) {
                        $('.order_placed_subject').text("Schedule Order");
                        $('.order_placed_msg').text("You have a new schedule order");
                    } else {
                        $('.order_placed_subject').text("New Order");
                        $('.order_placed_msg').text("You have a new order");
                    }

                    $("#notification_add_order_a").attr("href", "/orders/edit/" + order.id);

                    $('#notification_add_order').modal('show');
                    playNotificationSound();

                    // Auto reload only if user is on orders page
                    if (window.location.pathname.includes("/orders")) {
                        setTimeout(() => window.location.reload(), 7000);
                    }
                }

                // Main polling listener
                function startListener() {
                    setInterval(() => {
                        $.get(ENDPOINT_LATEST, function (res) {
                            const newest = res.latest_id ?? "";

                            // first run → only store latest, don't trigger popup
                            if (!lastOrderId) {
                                lastOrderId = newest;
                                return;
                            }

                            // New order compared to previous
                            if (newest && newest !== lastOrderId) {
                                $.get(ENDPOINT_ORDER(newest), function (order) {
                                    showOrderPopup(order);
                                });
                                lastOrderId = newest;
                            }
                        });
                    }, POLL);
                }

                $(document).ready(() => {
                    loadRingtone();
                    startListener();
                });

            })();
        </script>


        {{--        <script type="text/javascript">--}}
{{--            // Duplicate impersonation script removed - using the improved version above--}}
{{--            var version=database.collection('settings').doc("Version");--}}
{{--            var placeholder = database.collection('settings').doc('placeHolderImage');--}}
{{--                placeholder.get().then(async function (snapshotsimage) {--}}
{{--                    var placeholderImageData = snapshotsimage.data();--}}
{{--                    placeholderImage = placeholderImageData.image;--}}

{{--                })--}}
{{--            var globalSettings=database.collection('settings').doc("globalSettings");--}}
{{--            version.get().then(async function(snapshots) {--}}
{{--                var version_data=snapshots.data();--}}
{{--                if(version_data==undefined) {--}}
{{--                    database.collection('settings').doc('Version').set({});--}}
{{--                }--}}
{{--                try {--}}
{{--                    $('.web_version').html("V:"+version_data.web_version);--}}
{{--                } catch(error) {--}}
{{--                }--}}
{{--            });--}}
{{--            globalSettings.get().then(async function(snapshots) {--}}
{{--                var globalSettingsData=snapshots.data();--}}
{{--                if(globalSettingsData==undefined) {--}}
{{--                    database.collection('settings').doc('globalSettings').set({});--}}
{{--                }--}}
{{--                try {--}}
{{--                    if(getCookie('meta_title')==undefined||getCookie('meta_title')==null||getCookie(--}}
{{--                        'meta_title')=="") {--}}
{{--                        document.title=globalSettingsData.meta_title;--}}
{{--                        setCookie('meta_title',globalSettingsData.meta_title,365);--}}
{{--                    }--}}
{{--                    if(getCookie('favicon')==undefined||getCookie('favicon')==null||getCookie('favicon')==--}}
{{--                        "") {--}}
{{--                        setCookie('favicon',globalSettingsData.favicon,365);--}}
{{--                    }--}}
{{--                } catch(error) {--}}
{{--                }--}}
{{--            });--}}
{{--            function exportData(dt,format,config) {--}}
{{--                const {--}}
{{--                    columns,--}}
{{--                    fileName='Export',--}}
{{--                }=config;--}}

{{--                const filteredRecords=dt.ajax.json().filteredData;--}}

{{--                const fieldTypes={};--}}
{{--                const dataMapper=(record) => {--}}
{{--                    return columns.map((col) => {--}}
{{--                        const value=record[col.key];--}}
{{--                        if(!fieldTypes[col.key]) {--}}
{{--                            if(value===true||value===false) {--}}
{{--                                fieldTypes[col.key]='boolean';--}}
{{--                            } else if(value&&typeof value==='object'&&value.seconds) {--}}
{{--                                fieldTypes[col.key]='date';--}}
{{--                            } else if(typeof value==='number') {--}}
{{--                                fieldTypes[col.key]='number';--}}
{{--                            } else if(typeof value==='string') {--}}
{{--                                fieldTypes[col.key]='string';--}}
{{--                            } else {--}}
{{--                                fieldTypes[col.key]='string';--}}
{{--                            }--}}
{{--                        }--}}

{{--                        switch(fieldTypes[col.key]) {--}}
{{--                            case 'boolean':--}}
{{--                                return value? 'Yes':'No';--}}
{{--                            case 'date':--}}
{{--                                return value? new Date(value.seconds*1000).toLocaleString():'-';--}}
{{--                            case 'number':--}}
{{--                                return typeof value==='number'? value:0;--}}
{{--                            case 'string':--}}
{{--                            default:--}}
{{--                                return value||'-';--}}
{{--                        }--}}
{{--                    });--}}
{{--                };--}}

{{--                const tableData=filteredRecords.map(dataMapper);--}}

{{--                const data=[columns.map(col => col.header),...tableData];--}}

{{--                const columnWidths=columns.map((_,colIndex) =>--}}
{{--                    Math.max(...data.map(row => row[colIndex]?.toString().length||0))--}}
{{--                );--}}

{{--                if(format==='csv') {--}}
{{--                    const csv=data.map(row => row.map(cell => {--}}
{{--                        if(typeof cell==='string'&&(cell.includes(',')||cell.includes('\n')||cell.includes('"'))) {--}}
{{--                            return `"${cell.replace(/"/g,'""')}"`;--}}
{{--                        }--}}
{{--                        return cell;--}}
{{--                    }).join(',')).join('\n');--}}

{{--                    const blob=new Blob([csv],{type: 'text/csv;charset=utf-8;'});--}}
{{--                    saveAs(blob,`${fileName}.csv`);--}}
{{--                } else if(format==='excel') {--}}
{{--                    const ws=XLSX.utils.aoa_to_sheet(data,{cellDates: true});--}}

{{--                    ws['!cols']=columnWidths.map(width => ({wch: Math.min(width+5,30)}));--}}

{{--                    const wb=XLSX.utils.book_new();--}}
{{--                    XLSX.utils.book_append_sheet(wb,ws,'Data');--}}
{{--                    XLSX.writeFile(wb,`${fileName}.xlsx`);--}}
{{--                } else if(format==='pdf') {--}}
{{--                    const {jsPDF}=window.jspdf;--}}
{{--                    const doc=new jsPDF();--}}

{{--                    const totalLength=columnWidths.reduce((sum,length) => sum+length,0);--}}
{{--                    const columnStyles={};--}}
{{--                    columnWidths.forEach((length,index) => {--}}
{{--                        columnStyles[index]={--}}
{{--                            cellWidth: (length/totalLength)*180,--}}
{{--                        };--}}
{{--                    });--}}

{{--                    doc.setFontSize(16);--}}
{{--                    doc.text(fileName,14,16);--}}

{{--                    doc.autoTable({--}}
{{--                        head: [columns.map(col => col.header)],--}}
{{--                        body: tableData,--}}
{{--                        startY: 20,--}}
{{--                        theme: 'striped',--}}
{{--                        styles: {--}}
{{--                            cellPadding: 2,--}}
{{--                            fontSize: 10,--}}
{{--                        },--}}
{{--                        columnStyles,--}}
{{--                        margin: {top: 30,bottom: 30},--}}
{{--                        didDrawPage: function(data) {--}}
{{--                            doc.setFontSize(10);--}}
{{--                            doc.text(fileName,data.settings.margin.left,10);--}}
{{--                        }--}}
{{--                    });--}}
{{--                    doc.save(`${fileName}.pdf`);--}}
{{--                } else {--}}
{{--                    console.error('Unsupported format');--}}
{{--                }--}}
{{--            }--}}
{{--            database.collection('users').doc(cuser_id).get().then(async function(usersnapshots) {--}}
{{--                var userData=usersnapshots.data();--}}
{{--                var username=userData.firstName+' '+userData.lastName;--}}

{{--                if (!userData.hasOwnProperty('profilePictureURL') || userData.profilePictureURL === '' || userData.profilePictureURL === null || userData.profilePictureURL === "null") {--}}

{{--                    $('.profile-pic').attr('src', placeholderImage);--}}
{{--                }--}}
{{--                else--}}
{{--                {--}}

{{--                    $('.profile-pic').attr('src',userData.profilePictureURL);--}}
{{--                }--}}
{{--                $('#username').text(username);--}}
{{--                database.collection('vendors').where('author','==',cuser_id).get().then(function(snapshots) {--}}
{{--                    if(snapshots.docs.length>0) {--}}
{{--                        snapshots.forEach(function(doc) {--}}
{{--                            var data=doc.data();--}}
{{--                            // Assuming you have retrieved a Firestore value and stored it in a variable called 'value'--}}
{{--                            if(data.createdAt instanceof firebase.firestore.Timestamp) {--}}
{{--                                // 'value' is a Firestore timestamp--}}
{{--                            } else if(typeof data.createdAt==='object'&&!Array.isArray(data--}}
{{--                                .createdAt)) {--}}
{{--                                const combinedValue=(data.createdAt._seconds)*1000+(data--}}
{{--                                    .createdAt._nanoseconds/1000000);--}}
{{--                                const regularTimestamp=new Date(combinedValue);--}}
{{--                                doc.ref.update({--}}
{{--                                    "createdAt": regularTimestamp--}}
{{--                                });--}}
{{--                            }--}}
{{--                        });--}}
{{--                    }--}}
{{--                });--}}
{{--            });--}}
{{--            var orderPlacedSubject='';--}}
{{--            var orderPlacedMsg='';--}}
{{--            var dineInPlacedSubject='';--}}
{{--            var dineInPlacedMsg='';--}}
{{--            var driverAcceptedMsg='';--}}
{{--            var driverAcceptedSubject='';--}}
{{--            var scheduleOrderPlacedSubject='';--}}
{{--            var scheduleOrderPlacedMsg='';--}}
{{--            database.collection('dynamic_notification').get().then(async function(snapshot) {--}}
{{--                if(snapshot.docs.length>0) {--}}
{{--                    snapshot.docs.map(async (listval) => {--}}
{{--                        val=listval.data();--}}
{{--                        if(val.type=="dinein_placed") {--}}
{{--                            dineInPlacedSubject=val.subject;--}}
{{--                            dineInPlacedMsg=val.message;--}}
{{--                        } else if(val.type=="order_placed") {--}}
{{--                            orderPlacedSubject=val.subject;--}}
{{--                            orderPlacedMsg=val.message;--}}
{{--                        } else if(val.type=="driver_accepted") {--}}
{{--                            driverAcceptedSubject=val.subject;--}}
{{--                            driverAcceptedMsg=val.message;--}}
{{--                        } else if(val.type=="schedule_order") {--}}
{{--                            scheduleOrderPlacedSubject=val.subject;--}}
{{--                            scheduleOrderPlacedMsg=val.message;--}}
{{--                        }--}}
{{--                    });--}}
{{--                }--}}
{{--            });--}}
{{--            // Optimize Firebase operations for shared hosting--}}
{{--            var orderListener = database.collection('restaurant_orders').where('vendor.author',"==",cuser_id).onSnapshot(function(doc) {--}}
{{--                if(pageloadded) {--}}
{{--                    doc.docChanges().forEach(function(change) {--}}
{{--                        val=change.doc.data();--}}
{{--                        if(change.type=="added") {--}}
{{--                            if(val.status=="Order Placed") {--}}
{{--                                if(val.author.firstName) {--}}
{{--                                }--}}
{{--                                if(val.scheduleTime!=undefined&&val.scheduleTime!=null&&val--}}
{{--                                    .scheduleTime!='') {--}}
{{--                                    $('.order_placed_subject').text(scheduleOrderPlacedSubject);--}}
{{--                                    $('.order_placed_msg').text(scheduleOrderPlacedMsg);--}}
{{--                                } else {--}}
{{--                                    $('.order_placed_subject').text(orderPlacedSubject);--}}
{{--                                    $('.order_placed_msg').text(orderPlacedMsg);--}}
{{--                                }--}}
{{--                                if(route1) {--}}
{{--                                    jQuery("#notification_add_order_a").attr("href",route1.replace(':id',val--}}
{{--                                        .id));--}}
{{--                                }--}}
{{--                                jQuery("#notification_add_order").modal('show');--}}
{{--                            }--}}
{{--                        } else if(change.type=="modified") {--}}
{{--                            //change.status--}}
{{--                            if(val.status=="Order Placed") {--}}
{{--                                if(val.author.firstName) {--}}
{{--                                }--}}
{{--                                if(route1) {--}}
{{--                                    jQuery("#notification_add_order_a").attr("href",route1.replace(':id',val--}}
{{--                                        .id));--}}
{{--                                }--}}
{{--                                if(val.scheduleTime!=undefined&&val.scheduleTime!=null&&val--}}
{{--                                    .scheduleTime!='') {--}}
{{--                                    $('.order_placed_subject').text(scheduleOrderPlacedSubject);--}}
{{--                                    $('.order_placed_msg').text(scheduleOrderPlacedMsg);--}}
{{--                                } else {--}}
{{--                                    $('.order_placed_subject').text(orderPlacedSubject);--}}
{{--                                    $('.order_placed_msg').text(orderPlacedMsg);--}}
{{--                                }--}}
{{--                                jQuery("#notification_add_order").modal('show');--}}
{{--                            } else if(val.status=="Driver Accepted") {--}}
{{--                                if(val.driver&&val.driver.firstName) {--}}
{{--                                }--}}
{{--                                if(route1) {--}}
{{--                                    jQuery("#notification_accepted_a").attr("href",route1.replace(':id',val--}}
{{--                                        .id));--}}
{{--                                }--}}
{{--                                $('.driver_accepted_subject').text(driverAcceptedSubject);--}}
{{--                                $('.driver_accepted_msg').text(driverAcceptedMsg);--}}
{{--                                jQuery("#notification_accepted_order").modal('show');--}}
{{--                            }--}}
{{--                        }--}}
{{--                    });--}}
{{--                } else {--}}
{{--                    pageloadded=1;--}}
{{--                }--}}
{{--            });--}}
{{--            var pageloadded_book=0;--}}
{{--            // Optimize Firebase operations for shared hosting--}}
{{--            var tableListener = database.collection('booked_table').where('vendor.author',"==",cuser_id).onSnapshot(function(doc) {--}}
{{--                if(pageloadded_book) {--}}
{{--                    doc.docChanges().forEach(function(change) {--}}
{{--                        val=change.doc.data();--}}
{{--                        if(change.type=="added") {--}}
{{--                            if(val.status=="Order Placed") {--}}
{{--                                if(val.author.firstName) {--}}
{{--                                }--}}
{{--                                if(route1) {--}}
{{--                                    jQuery("#notification_book_table_add_order_a").attr("href",booktable--}}
{{--                                        .replace(':id',val.id));--}}
{{--                                }--}}
{{--                                $('.dinein_order_placed_subject').text(dineInPlacedSubject);--}}
{{--                                $('.dinein_order_placed_msg').text(dineInPlacedMsg);--}}
{{--                                jQuery("#notification_book_table_add_order").modal('show');--}}
{{--                            }--}}
{{--                        }--}}
{{--                    });--}}
{{--                } else {--}}
{{--                    pageloadded_book=1;--}}
{{--                }--}}
{{--            });--}}
{{--            var langcount=0;--}}
{{--            var languages_list=database.collection('settings').doc('languages');--}}
{{--            languages_list.get().then(async function(snapshotslang) {--}}
{{--                snapshotslang=snapshotslang.data();--}}
{{--                if(snapshotslang!=undefined) {--}}
{{--                    snapshotslang=snapshotslang.list;--}}
{{--                    languages_list_main=snapshotslang;--}}
{{--                    snapshotslang.forEach((data) => {--}}
{{--                        if(data.isActive==true) {--}}
{{--                            langcount++;--}}
{{--                            $('#language_dropdown').append($("<option></option>").attr("value",data.slug)--}}
{{--                                .text(data.title));--}}
{{--                        }--}}
{{--                    });--}}
{{--                    if(langcount>1) {--}}
{{--                        $("#language_dropdown_box").css('visibility','visible');--}}
{{--                    }--}}
{{--                    <?php if (session()->get('locale')) { ?>--}}
{{--                    $("#language_dropdown").val("<?php    echo session()->get('locale'); ?>");--}}
{{--                    <?php } ?>--}}
{{--                }--}}
{{--            });--}}
{{--            var url="{{ route('changeLang') }}";--}}
{{--            $(".changeLang").change(function() {--}}
{{--                var slug=$(this).val();--}}
{{--                languages_list_main.forEach((data) => {--}}
{{--                    if(slug==data.slug) {--}}
{{--                        if(data.is_rtl==undefined) {--}}
{{--                            setCookie('is_rtl','false',365);--}}
{{--                        } else {--}}
{{--                            setCookie('is_rtl',data.is_rtl.toString(),365);--}}
{{--                        }--}}
{{--                        window.location.href=url+"?lang="+slug;--}}
{{--                    }--}}
{{--                });--}}
{{--            });--}}
{{--            function setCookie(name,value,days) {--}}
{{--                var expires="";--}}
{{--                if(days) {--}}
{{--                    var date=new Date();--}}
{{--                    date.setTime(date.getTime()+(days*24*60*60*1000));--}}
{{--                    expires="; expires="+date.toUTCString();--}}
{{--                }--}}
{{--                document.cookie=name+"="+(value||"")+expires+"; path=/";--}}
{{--            }--}}
{{--            function getCookie(name) {--}}
{{--                var nameEQ=name+"=";--}}
{{--                var ca=document.cookie.split(';');--}}
{{--                for(var i=0;i<ca.length;i++) {--}}
{{--                    var c=ca[i];--}}
{{--                    while(c.charAt(0)==' ') c=c.substring(1,c.length);--}}
{{--                    if(c.indexOf(nameEQ)==0) return c.substring(nameEQ.length,c.length);--}}
{{--                }--}}
{{--                return null;--}}
{{--            }--}}
{{--            database.collection('settings').doc("notification_setting").get().then(async function(snapshots) {--}}
{{--                var data=snapshots.data();--}}
{{--                if(data!=undefined) {--}}
{{--                    serviceJson=data.serviceJson;--}}
{{--                    if(serviceJson!=''&&serviceJson!=null) {--}}
{{--                        $.ajax({--}}
{{--                            type: 'POST',--}}
{{--                            data: {--}}
{{--                                serviceJson: btoa(serviceJson),--}}
{{--                            },--}}
{{--                            url: "{{ route('store-firebase-service') }}",--}}
{{--                            headers: {--}}
{{--                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')--}}
{{--                            },--}}
{{--                            success: function(data) {}--}}
{{--                        });--}}
{{--                    }--}}
{{--                }--}}
{{--            });--}}
{{--            var refDistance = database.collection('settings').doc("RestaurantNearBy");--}}
{{--            refDistance.get().then(async function (snapshots) {--}}
{{--                try {--}}
{{--                    var data = snapshots.data();--}}
{{--                    var distanceType=data.distanceType.charAt(0).toUpperCase() + data.distanceType.slice(1);--}}
{{--                    $('#distanceType').val(distanceType);--}}
{{--                    $('.global_distance_type').html(distanceType);--}}

{{--                } catch (error) {--}}

{{--                }--}}
{{--            });--}}
{{--            //On delete item delete image also from bucket general code--}}
{{--            const deleteDocumentWithImage=async (collection,id,singleImageField,arrayImageField) => {--}}
{{--                // Reference to the Firestore document--}}
{{--                const docRef=database.collection(collection).doc(id);--}}
{{--                try {--}}
{{--                    const doc=await docRef.get();--}}
{{--                    if(!doc.exists) {--}}
{{--                        console.log("No document found for deletion");--}}
{{--                        return;--}}
{{--                    }--}}
{{--                    const data=doc.data();--}}
{{--                    // Deleting single image field--}}
{{--                    if(singleImageField) {--}}
{{--                        if(Array.isArray(singleImageField)) {--}}
{{--                            for(const field of singleImageField) {--}}
{{--                                const imageUrl=data[field];--}}
{{--                                if(imageUrl) await deleteImageFromBucket(imageUrl);--}}
{{--                            }--}}
{{--                        } else {--}}
{{--                            const imageUrl=data[singleImageField];--}}
{{--                            if(imageUrl) await deleteImageFromBucket(imageUrl);--}}
{{--                        }--}}
{{--                    }--}}
{{--                    // Deleting array image field--}}
{{--                    if(arrayImageField) {--}}
{{--                        if(Array.isArray(arrayImageField)) {--}}
{{--                            for(const field of arrayImageField) {--}}
{{--                                const arrayImages=data[field];--}}
{{--                                if(arrayImages&&Array.isArray(arrayImages)) {--}}
{{--                                    for(const imageUrl of arrayImages) {--}}
{{--                                        if(imageUrl) await deleteImageFromBucket(imageUrl);--}}
{{--                                    }--}}
{{--                                }--}}
{{--                            }--}}
{{--                        } else {--}}
{{--                            const arrayImages=data[arrayImageField];--}}
{{--                            if(arrayImages&&Array.isArray(arrayImages)) {--}}
{{--                                for(const imageUrl of arrayImages) {--}}
{{--                                    if(imageUrl) await deleteImageFromBucket(imageUrl);--}}
{{--                                }--}}
{{--                            }--}}
{{--                        }--}}
{{--                    }--}}
{{--                    // Deleting images in variants array within item_attribute--}}
{{--                    const item_attribute=data.item_attribute||{};  // Access item_attribute--}}
{{--                    const variants=item_attribute.variants||[];    // Access variants array inside item_attribute--}}
{{--                    if(variants.length>0) {--}}
{{--                        for(const variant of variants) {--}}
{{--                            const variantImageUrl=variant.variant_image;--}}
{{--                            if(variantImageUrl) {--}}
{{--                                await deleteImageFromBucket(variantImageUrl);--}}
{{--                            }--}}
{{--                        }--}}
{{--                    }--}}
{{--                    // Optionally delete the Firestore document after image deletion--}}
{{--                    await docRef.delete();--}}
{{--                    console.log("Document and images deleted successfully.");--}}
{{--                } catch(error) {--}}
{{--                    console.error("Error deleting document and images:",error);--}}
{{--                }--}}
{{--            };--}}
{{--        </script>--}}

        <!-- Cache-based Impersonation Script -->
        <script>
        /**
         * Cache-based Impersonation Script for Restaurant Panel
         */
        (function() {
            'use strict';

            console.log('🔍 Cache-based impersonation script loaded');

            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeImpersonation);
            } else {
                initializeImpersonation();
            }

            function initializeImpersonation() {
                console.log('🔍 Initializing cache-based impersonation check...');
                checkImpersonationSession();
            }

            function checkImpersonationSession() {
                console.log('🔍 Checking for impersonation session...');

                // Get impersonation key from URL
                const urlParams = new URLSearchParams(window.location.search);
                const impersonationKey = urlParams.get('impersonation_key');

                if (!impersonationKey) {
                    console.log('ℹ️ No impersonation key found in URL');
                    return;
                }

                console.log('🔍 Impersonation key found:', impersonationKey);

                fetch('/api/check-impersonation?impersonation_key=' + encodeURIComponent(impersonationKey), {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.has_impersonation) {
                        console.log('✅ Impersonation session detected!');
                        console.log('Restaurant UID:', data.restaurant_uid);
                        console.log('Restaurant Name:', data.restaurant_name);

                        showImpersonationLoading();
                        processImpersonation(data.cache_key);
                    } else {
                        console.log('ℹ️ No valid impersonation session found');
                    }
                })
                .catch(error => {
                    console.error('❌ Error checking impersonation session:', error);
                });
            }

            function processImpersonation(cacheKey) {
                console.log('🚀 Processing impersonation...');

                fetch('/api/process-impersonation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCSRFToken()
                    },
                    body: JSON.stringify({
                        cache_key: cacheKey
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        console.log('✅ Impersonation successful!');
                        console.log('Restaurant:', data.restaurant_name);

                        showImpersonationSuccess(data.restaurant_name);

                        setTimeout(() => {
                            console.log('🔄 Reloading page to apply impersonation...');
                            window.location.reload();
                        }, 2000);
                    } else {
                        console.error('❌ Impersonation failed:', data.message);
                        showImpersonationError(data.message);
                    }
                })
                .catch(error => {
                    console.error('❌ Error processing impersonation:', error);
                    showImpersonationError('Error processing impersonation: ' + error.message);
                });
            }

            function getCSRFToken() {
                const token = document.querySelector('meta[name="csrf-token"]');
                return token ? token.getAttribute('content') : '';
            }

            function showImpersonationLoading() {
                const existing = document.getElementById('impersonation-loading');
                if (existing) existing.remove();

                const loading = document.createElement('div');
                loading.id = 'impersonation-loading';
                loading.innerHTML = `
                    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; justify-content: center; align-items: center;">
                        <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; max-width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                            <div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                            <h3 style="margin: 0 0 10px 0; color: #333;">🔐 Admin Impersonation</h3>
                            <p style="margin: 0; color: #666;">Processing impersonation...</p>
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
                const loading = document.getElementById('impersonation-loading');
                if (loading) loading.remove();

                const success = document.createElement('div');
                success.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-left: 4px solid #28a745;">
                        <strong>✅ Impersonation Successful!</strong><br>
                        You are now logged in as <strong>${restaurantName}</strong>
                    </div>
                `;
                document.body.appendChild(success);

                setTimeout(() => {
                    if (success.parentNode) success.remove();
                }, 5000);
            }

            function showImpersonationError(message) {
                const loading = document.getElementById('impersonation-loading');
                if (loading) loading.remove();

                const error = document.createElement('div');
                error.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-left: 4px solid #dc3545;">
                        <strong>❌ Impersonation Failed</strong><br>
                        ${message}
                    </div>
                `;
                document.body.appendChild(error);

                setTimeout(() => {
                    if (error.parentNode) error.remove();
                }, 8000);
            }

            // Add impersonation status indicator
            function addImpersonationStatusIndicator() {
                fetch('/api/impersonation-status', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.is_impersonated) {
                        const indicator = document.createElement('div');
                        indicator.innerHTML = `
                            <div style="position: fixed; top: 0; left: 0; right: 0; background: #fff3cd; color: #856404; padding: 10px; text-align: center; z-index: 1000; border-bottom: 1px solid #ffeaa7; font-size: 14px;">
                                <strong>🔐 Admin Impersonation Active</strong> - You are logged in as <strong>${data.restaurant_name}</strong> for support purposes.
                                <button onclick="endImpersonation()" style="margin-left: 15px; background: #856404; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;">End Impersonation</button>
                            </div>
                        `;
                        document.body.insertBefore(indicator, document.body.firstChild);
                        document.body.style.paddingTop = '50px';
                    }
                })
                .catch(error => {
                    console.error('Error checking impersonation status:', error);
                });
            }

            // Function to end impersonation
            window.endImpersonation = function() {
                fetch('/api/end-impersonation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCSRFToken()
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('✅ Impersonation ended successfully');
                        window.location.reload();
                    } else {
                        console.error('❌ Error ending impersonation:', data.message);
                        alert('Error ending impersonation: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('❌ Error ending impersonation:', error);
                    alert('Error ending impersonation: ' + error.message);
                });
            };

            // Add status indicator after a short delay
            setTimeout(addImpersonationStatusIndicator, 1000);

        })();
        </script>

        <!-- Impersonation Banner -->
        <script>
        // Check if user is impersonated and show notification
        (function() {
            const impersonationData = localStorage.getItem('restaurant_impersonation');
            if (impersonationData) {
                try {
                    const data = JSON.parse(impersonationData);
                    if (data.isImpersonated) {
                        const banner = document.createElement('div');
                        banner.innerHTML = `
                            <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin-bottom: 20px; border-radius: 5px; position: relative; z-index: 1000;">
                                <strong>🔐 Admin Impersonation Active</strong><br>
                                You are currently logged in as this restaurant owner for support purposes.<br>
                                <small>Impersonated at: ${new Date(data.impersonatedAt).toLocaleString()}</small>
                                <button onclick="this.parentElement.parentElement.remove()" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 18px; cursor: pointer;">&times;</button>
                            </div>
                        `;
                        const main = document.querySelector('main');
                        if (main && main.firstChild) {
                            main.insertBefore(banner, main.firstChild);
                        } else if (main) {
                            main.appendChild(banner);
                        }
                    }
                } catch (error) {
                    console.error('Error parsing impersonation data:', error);
                }
            }
        })();
        </script>

        <!-- Impersonation Script -->
        <script>
        /**
         * Simple Session-Based Impersonation Script for Restaurant Panel
         *
         * This script checks for impersonation sessions and processes them automatically.
         * No URL parameters needed - everything is handled server-side.
         */
        (function() {
            'use strict';

            console.log('🔍 Session-based impersonation script loaded');

            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeImpersonation);
            } else {
                initializeImpersonation();
            }

            function initializeImpersonation() {
                console.log('🔍 Initializing session-based impersonation check...');

                // Check if there's an impersonation session
                checkImpersonationSession();
            }

            function checkImpersonationSession() {
                console.log('🔍 Checking for impersonation session...');

                // Get impersonation key from URL
                const urlParams = new URLSearchParams(window.location.search);
                const impersonationKey = urlParams.get('impersonation_key');

                if (!impersonationKey) {
                    console.log('ℹ️ No impersonation key found in URL');
                    return;
                }

                console.log('🔍 Impersonation key found:', impersonationKey);

                fetch('/api/check-impersonation?impersonation_key=' + encodeURIComponent(impersonationKey), {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.has_impersonation) {
                        console.log('✅ Impersonation session detected!');
                        console.log('Restaurant UID:', data.restaurant_uid);
                        console.log('Restaurant Name:', data.restaurant_name);

                        showImpersonationLoading();
                        processImpersonation(data.cache_key);
                    } else {
                        console.log('ℹ️ No valid impersonation session found');
                    }
                })
                .catch(error => {
                    console.error('❌ Error checking impersonation session:', error);
                });
            }

            function processImpersonation(cacheKey) {
                console.log('🚀 Processing impersonation...');

                fetch('/api/process-impersonation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCSRFToken()
                    },
                    body: JSON.stringify({
                        cache_key: cacheKey
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        console.log('✅ Impersonation successful!');
                        console.log('Restaurant:', data.restaurant_name);

                        showImpersonationSuccess(data.restaurant_name);

                        setTimeout(() => {
                            console.log('🔄 Reloading page to apply impersonation...');
                            window.location.reload();
                        }, 2000);
                    } else {
                        console.error('❌ Impersonation failed:', data.message);
                        showImpersonationError(data.message);
                    }
                })
                .catch(error => {
                    console.error('❌ Error processing impersonation:', error);
                    showImpersonationError('Error processing impersonation: ' + error.message);
                });
            }

            function getCSRFToken() {
                const token = document.querySelector('meta[name="csrf-token"]');
                return token ? token.getAttribute('content') : '';
            }

            function showImpersonationLoading() {
                // Remove any existing loading indicator
                const existing = document.getElementById('impersonation-loading');
                if (existing) {
                    existing.remove();
                }

                const loading = document.createElement('div');
                loading.id = 'impersonation-loading';
                loading.innerHTML = `
                    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; justify-content: center; align-items: center;">
                        <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; max-width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                            <div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                            <h3 style="margin: 0 0 10px 0; color: #333;">🔐 Admin Impersonation</h3>
                            <p style="margin: 0; color: #666;">Processing impersonation...</p>
                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #999;">Please wait while we authenticate you.</p>
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
                // Remove loading indicator
                const loading = document.getElementById('impersonation-loading');
                if (loading) {
                    loading.remove();
                }

                const success = document.createElement('div');
                success.id = 'impersonation-success';
                success.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-left: 4px solid #28a745;">
                        <strong>✅ Impersonation Successful!</strong><br>
                        You are now logged in as <strong>${restaurantName}</strong>
                        <button onclick="this.parentElement.parentElement.remove()" style="float: right; background: none; border: none; font-size: 18px; cursor: pointer; margin-left: 10px; color: #155724;">&times;</button>
                    </div>
                `;
                document.body.appendChild(success);

                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (success.parentNode) {
                        success.remove();
                    }
                }, 5000);
            }

            function showImpersonationError(message) {
                // Remove loading indicator
                const loading = document.getElementById('impersonation-loading');
                if (loading) {
                    loading.remove();
                }

                const error = document.createElement('div');
                error.id = 'impersonation-error';
                error.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-left: 4px solid #dc3545;">
                        <strong>❌ Impersonation Failed</strong><br>
                        ${message}
                        <button onclick="this.parentElement.parentElement.remove()" style="float: right; background: none; border: none; font-size: 18px; cursor: pointer; margin-left: 10px; color: #721c24;">&times;</button>
                    </div>
                `;
                document.body.appendChild(error);

                // Auto-remove after 8 seconds
                setTimeout(() => {
                    if (error.parentNode) {
                        error.remove();
                    }
                }, 8000);
            }

            // Add impersonation status indicator to the page
            function addImpersonationStatusIndicator() {
                // Check if user is currently impersonated
                fetch('/api/impersonation-status', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.is_impersonated) {
                        const indicator = document.createElement('div');
                        indicator.id = 'impersonation-indicator';
                        indicator.innerHTML = `
                            <div style="position: fixed; top: 0; left: 0; right: 0; background: #fff3cd; color: #856404; padding: 10px; text-align: center; z-index: 1000; border-bottom: 1px solid #ffeaa7; font-size: 14px;">
                                <strong>🔐 Admin Impersonation Active</strong> - You are logged in as <strong>${data.restaurant_name}</strong> for support purposes.
                                <button onclick="endImpersonation()" style="margin-left: 15px; background: #856404; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;">End Impersonation</button>
                            </div>
                        `;
                        document.body.insertBefore(indicator, document.body.firstChild);

                        // Adjust body padding to account for the indicator
                        document.body.style.paddingTop = '50px';
                    }
                })
                .catch(error => {
                    console.error('Error checking impersonation status:', error);
                });
            }

            // Function to end impersonation
            window.endImpersonation = function() {
                fetch('/api/end-impersonation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCSRFToken()
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('✅ Impersonation ended successfully');
                        // Reload the page
                        window.location.reload();
                    } else {
                        console.error('❌ Error ending impersonation:', data.message);
                        alert('Error ending impersonation: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('❌ Error ending impersonation:', error);
                    alert('Error ending impersonation: ' + error.message);
                });
            };

            // Add status indicator after a short delay
            setTimeout(addImpersonationStatusIndicator, 1000);

            // Ensure Firebase is ready before dashboard operations
            function ensureFirebaseReady() {
                return new Promise((resolve) => {
                    if (window.database && window.auth) {
                        resolve();
                    } else {
                        const checkFirebase = setInterval(() => {
                            if (window.database && window.auth) {
                                clearInterval(checkFirebase);
                                resolve();
                            }
                        }, 100);

                        // Timeout after 5 seconds
                        setTimeout(() => {
                            clearInterval(checkFirebase);
                            console.warn('Firebase initialization timeout');
                            resolve();
                        }, 5000);
                    }
                });
            }

            // Make Firebase ready function globally available
            window.ensureFirebaseReady = ensureFirebaseReady;

        })();
        </script>
    </body>

</html>
