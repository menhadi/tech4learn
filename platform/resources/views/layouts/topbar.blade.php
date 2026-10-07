<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <!-- LOGO -->
                <div class="navbar-brand-box horizontal-logo">
                    @include('partials.brand-logo', ['brandHref' => route('dashboard'), 'brandClass' => 'is-sidebar'])
                </div>

                <button type="button"
                    class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger shadow-none"
                    id="topnav-hamburger-icon"
                    aria-controls="navbar-nav"
                    aria-expanded="true"
                    aria-label="Collapse sidebar"
                    title="Collapse sidebar">
                    <i class="ri-menu-fold-line fs-22 sidebar-toggle-icon" aria-hidden="true"></i>
                    <span class="visually-hidden sidebar-toggle-text">Collapse sidebar</span>
                </button>
            </div>

            <div class="d-flex align-items-center">

                @include('partials.language-switcher')

                <div class="dropdown d-md-none topbar-head-dropdown header-item">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle shadow-none"
                        id="page-header-search-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false">
                        <i class="bx bx-search fs-22"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                        aria-labelledby="page-header-search-dropdown">
                        <form class="p-3">
                            <div class="form-group m-0">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search ..."
                                        aria-label="Recipient's username">
                                    <button class="btn btn-primary" type="submit"><i
                                            class="mdi mdi-magnify"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle shadow-none"
                        data-toggle="fullscreen">
                        <i class='bx bx-fullscreen fs-22'></i>
                    </button>
                </div>

                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button"
                        class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle light-dark-mode shadow-none">
                        <i class='bx bx-moon fs-22'></i>
                    </button>
                </div>



                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn shadow-none" id="page-header-user-dropdown"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <span class="text-start ms-xl-2">
                                <span
                                    class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{Auth::user()->name}}</span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item " href="javascript:void();"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i
                                class="bx bx-power-off font-size-16 align-middle me-1"></i> <span
                                key="t-logout">@lang('translation.logout')</span></a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
