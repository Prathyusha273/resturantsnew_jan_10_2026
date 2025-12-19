@php
    $logoUrl = $layoutBranding['appLogo'] ?? asset('/images/logo_web.png');
    $iconLogo = $layoutBranding['favicon'] ?? asset('images/logo-light-icon.png');
@endphp

<div class="navbar-header">
    <a class="navbar-brand LogoRedirection" href="{{ url('/') }}">
        <b>
            <img src="{{ $logoUrl }}" alt="homepage" class="dark-logo" id="logo_web" style="max-width: 150px;">
            <img src="{{ $iconLogo }}" alt="homepage" class="light-logo" style="max-width: 45px;">
        </b>
    </a>
</div>

<div class="navbar-collapse">

    <ul class="navbar-nav mr-auto mt-md-0">

        <li class="nav-item"> <a class="nav-link nav-toggler hidden-md-up text-muted waves-effect waves-dark" href="javascript:void(0)"><i class="mdi mdi-menu"></i></a> </li>

        <li class="nav-item m-l-10"> <a class="nav-link sidebartoggler hidden-sm-down text-muted waves-effect waves-dark" href="javascript:void(0)"><i class="ti-menu"></i></a> </li>

    </ul>



     <div style="visibility: hidden;" class="language-list icon d-flex align-items-center text-light ml-2" id="language_dropdown_box">

        <div class="language-select">

            <i class="fa fa-globe"></i>

        </div>

        <div class="language-options">

            <select class="form-control changeLang text-dark" id="language_dropdown">



            </select>

        </div>

    </div>

    <ul class="navbar-nav my-lg-0">



        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-muted waves-effect waves-dark" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="{{ asset('/images/users/user-new.png') }}" alt="user" class="profile-pic">
            </a>

            <div class="dropdown-menu dropdown-menu-right scale-up">
                <ul class="dropdown-user">
                    <li>
                        <div class="dw-user-box">
                            <div class="u-img">
                                <img class="profile-pic" src="{{ asset('/images/users/user-2.png') }}" alt="user" style="max-width: 45px;">
                            </div>
                            <div class="u-text">
                                <h4>{{ $layoutUser?->name ?? auth()->user()?->name ?? 'Account' }}</h4>
                                <p class="text-muted mb-0">{{ $layoutUser?->email ?? auth()->user()?->email }}</p>
                            </div>
                        </div>
                    </li>

                    <li role="separator" class="divider"></li>

                    <li><a href="{{ route('user.profile') }}"><i class="ti-user"></i>  {!! trans('lang.user_profile') !!}</a></li>

                    <li role="separator" class="divider"></li>

                    <li>
                        <a href="#"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="fa fa-power-off"></i> {{ __('Logout') }}
                        </a>

                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
                            @csrf
                        </form>
                    </li>

                </ul>

            </div>

        </li>

    </ul>

</div>

