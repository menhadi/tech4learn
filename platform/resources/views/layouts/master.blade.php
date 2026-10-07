<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-topbar="light"
    data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable">
@php
$configuration = getConfiguration();
$sidebarStorageKey = Auth::guard('student')->check()
    ? 'examelite-student-sidebar-size'
    : 'examelite-admin-sidebar-size';
$showAdminAiContentTools = auth()->check()
    && ! request()->is('student*')
    && ! request()->is('guest*')
    && ! request()->routeIs('student.*')
    && ! request()->routeIs('guest.*');

$themePrimary = $configuration->theme_primary_color ?? '#0f766e';
$themeSecondary = $configuration->theme_secondary_color ?? '#f59e0b';
$themeRgb = function ($hex, $fallback) {
    $hex = trim((string) $hex);
    if (substr($hex, 0, 1) === '#') {
        $hex = substr($hex, 1);
    }
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return $fallback;
    }

    return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
};
$themePrimaryRgb = $themeRgb($themePrimary, '15, 118, 110');
$themeSecondaryRgb = $themeRgb($themeSecondary, '245, 158, 11');
@endphp

<head>
    <meta charset="utf-8" />
    @if(!($subcategoriesEnabled ?? true))<style>[data-subcategory-ui]{display:none!important}</style>@endif
    <title>@yield('title') | {{ $configuration->name ?? '' }} </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $configuration->meta_content ?? 'Default description' }}" />
    <meta name="keywords" content="{{ $configuration->meta_keyword ?? 'Default keywords' }}" />
    <meta name="title" content="{{ $configuration->meta_title ?? 'Default title' }}" />
    <meta content="{{ $configuration->name ?? 'ExamFrame' }}" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (function () {
            try {
                const html = document.documentElement;
                const savedTheme = localStorage.getItem('examelite-admin-theme');
                if (savedTheme === 'dark' || savedTheme === 'light') {
                    html.setAttribute('data-bs-theme', savedTheme);
                    sessionStorage.setItem('data-bs-theme', savedTheme);
                }

                if (window.innerWidth >= 992) {
                    const savedSize = localStorage.getItem(@json($sidebarStorageKey));
                    if (savedSize === 'sm' || savedSize === 'lg') {
                        html.setAttribute('data-sidebar-size', savedSize);
                        sessionStorage.setItem('data-sidebar-size', savedSize);
                    }
                }

                if (sessionStorage.getItem('defaultAttribute')) {
                    const currentAttributes = {};
                    Array.from(html.attributes).forEach(function (attribute) {
                        currentAttributes[attribute.name] = attribute.value;
                    });
                    sessionStorage.setItem('defaultAttribute', JSON.stringify(currentAttributes));
                }
            } catch (error) {
                // Storage can be unavailable in privacy-restricted browsers.
            }
        })();
    </script>

    @if(isset($configuration->favicon))
        <link rel="shortcut icon" href="{{ asset('storage/' . $configuration->favicon) }}" type="image/x-icon">
    @else
        <link rel="shortcut icon" href="{{ URL::asset('build/images/favicon.ico') }}" type="image/x-icon">
    @endif

    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
    
    @hasSection('mathlive')
        {{-- Loaded only for the question editor. --}}
        <script defer src="//unpkg.com/mathlive"></script>
    @endif

    @include('layouts.head-css')

    @hasSection('rich-editor')
        @include('ckeditor')
    @endif

<style>
    :root {
        --vz-primary: {{ $themePrimary }};
        --vz-primary-rgb: {{ $themePrimaryRgb }};
        --vz-success: {{ $themePrimary }};
        --vz-success-rgb: {{ $themePrimaryRgb }};
        --vz-info: {{ $themeSecondary }};
        --vz-info-rgb: {{ $themeSecondaryRgb }};
        --vz-secondary: {{ $themeSecondary }};
        --vz-secondary-rgb: {{ $themeSecondaryRgb }};
        --vz-warning: {{ $themeSecondary }};
        --vz-warning-rgb: {{ $themeSecondaryRgb }};
        --vz-link-color: {{ $themePrimary }};
        --vz-link-hover-color: {{ $themePrimary }};
        --el-primary: {{ $themePrimary }};
        --el-primary-rgb: {{ $themePrimaryRgb }};
        --el-secondary: {{ $themeSecondary }};
        --el-secondary-rgb: {{ $themeSecondaryRgb }};
        --el-primary-soft: rgba({{ $themePrimaryRgb }}, 0.12);
        --el-secondary-soft: rgba({{ $themeSecondaryRgb }}, 0.14);
        --el-border: #d7e2df;
        --el-muted: #7b8497;
        --el-surface-muted: #f8fafc;
        --el-radius: 4px;
    }

    .btn-primary,
    .bg-primary {
        --vz-btn-bg: var(--el-primary);
        --vz-btn-border-color: var(--el-primary);
        --vz-btn-hover-bg: var(--el-primary);
        --vz-btn-hover-border-color: var(--el-primary);
        background-color: var(--el-primary) !important;
        border-color: var(--el-primary) !important;
    }

    .btn-info,
    .bg-info,
    .btn-success,
    .bg-success {
        --vz-btn-bg: var(--el-primary);
        --vz-btn-border-color: var(--el-primary);
        --vz-btn-hover-bg: var(--el-primary);
        --vz-btn-hover-border-color: var(--el-primary);
        background-color: var(--el-primary) !important;
        border-color: var(--el-primary) !important;
    }

    .btn-warning,
    .bg-warning {
        --vz-btn-bg: var(--el-secondary);
        --vz-btn-border-color: var(--el-secondary);
        --vz-btn-hover-bg: var(--el-secondary);
        --vz-btn-hover-border-color: var(--el-secondary);
        background-color: var(--el-secondary) !important;
        border-color: var(--el-secondary) !important;
    }

    .text-primary {
        color: var(--el-primary) !important;
    }

    .text-info,
    .text-success {
        color: var(--el-primary) !important;
    }

    .text-warning {
        color: var(--el-secondary) !important;
    }

    .pagination svg,
    .page-link svg {
        height: 1rem !important;
        width: 1rem !important;
    }

    .form-check-input,
    input[type="checkbox"],
    input[type="radio"] {
        accent-color: var(--el-primary);
    }

    .form-check-input {
        border-color: rgba(var(--el-primary-rgb), 0.65);
    }

    .form-check-input:checked {
        background-color: var(--el-primary) !important;
        border-color: var(--el-primary) !important;
    }

    .form-check-input:focus,
    input[type="checkbox"]:focus,
    input[type="radio"]:focus {
        border-color: var(--el-primary) !important;
        box-shadow: 0 0 0 0.2rem rgba(var(--el-primary-rgb), 0.18) !important;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected],
    .select2-container--default .select2-results__option--highlighted[data-selected],
    .select2-container--default .select2-results__option[aria-selected="true"],
    .select2-container--default .select2-results__option[data-selected="true"] {
        background-color: var(--el-primary) !important;
        color: #fff !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: var(--el-primary-soft) !important;
        border-color: rgba(var(--el-primary-rgb), 0.28) !important;
        color: var(--el-primary) !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        background: transparent !important;
        border-right-color: rgba(var(--el-primary-rgb), 0.24) !important;
        color: var(--el-primary) !important;
        opacity: 1 !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover,
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:focus {
        background: rgba(var(--el-primary-rgb), 0.12) !important;
        color: var(--el-primary) !important;
    }

    .choices__button,
    .choices__button:hover,
    .choices__button:focus {
        color: var(--el-primary) !important;
        filter: none !important;
        opacity: 1 !important;
    }

    .select2-container--default .select2-selection--single:focus,
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: var(--el-primary) !important;
        box-shadow: 0 0 0 0.2rem rgba(var(--el-primary-rgb), 0.14) !important;
    }

    /* Keep TinyMCE code/source modal usable across admin pages. */
    .tox-tinymce-aux,
    .tox-dialog-wrap,
    .tox-dialog,
    .tox-dialog__body,
    .tox-dialog__body-content {
        pointer-events: auto !important;
    }

    .tox-tinymce-aux {
        z-index: 9999 !important;
    }

    .tox-dialog__body-content {
        max-height: 70vh !important;
        overflow: auto !important;
    }

    .tox-dialog textarea,
    .tox-dialog__body-content textarea,
    .tox-textarea,
    .tox-textarea-wrap textarea {
        display: block !important;
        width: 100% !important;
        min-height: 420px !important;
        height: 60vh !important;
        color: var(--el-text, #0f172a) !important;
        background: #ffffff !important;
        border: 1px solid var(--el-border, #cbd5e1) !important;
        opacity: 1 !important;
        pointer-events: auto !important;
        resize: vertical !important;
        user-select: text !important;
    }

    .tox-dialog__footer .tox-button {
        background: var(--el-primary) !important;
        border-color: var(--el-primary) !important;
        color: #ffffff !important;
    }

    .tox-dialog__footer .tox-button--secondary {
        background: var(--el-secondary) !important;
        border-color: var(--el-secondary) !important;
        color: #111827 !important;
    }
</style>

<style>
    body.modal-open .ai-content-btn,
    body.modal-open .generate-ai-content-btn,
    body.modal-open button[data-ai-content],
    body.swal2-shown .ai-content-btn,
    body.swal2-shown .generate-ai-content-btn,
    body.swal2-shown button[data-ai-content] {
        display: none !important;
        visibility: hidden !important;
        pointer-events: none !important;
    }
</style>


<style>
    body.modal-open .ai-inline-generate-btn,
    body.swal2-shown .ai-inline-generate-btn,
    body.modal-open .btn-ai-inline,
    body.swal2-shown .btn-ai-inline {
        display: none !important;
        visibility: hidden !important;
        pointer-events: none !important;
    }
</style>
</head>

@section('body')
    @include('layouts.body')
@show

<div id="layout-wrapper">
    @if(Auth::guard('student')->check())
        @include('students.layouts.student-topbar')
        @include('students.layouts.sidebar')
    @else
        @include('layouts.topbar')
        @include('layouts.sidebar')
    @endif

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </div>
        @include('layouts.footer')
    </div>
</div>

{{-- 1. jQuery --}}
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    window.ExamLiteAjaxFilter = (function () {
        const timers = new WeakMap();

        function clearWorking(root = document) {
            root.querySelectorAll('.el-filter-working').forEach(function (element) {
                element.classList.remove('el-filter-working');
            });
        }

        function formUrl(form) {
            const url = new URL(form.action || window.location.href, window.location.origin);
            const formData = new FormData(form);
            url.search = '';

            formData.forEach(function (value, key) {
                if (value !== null && String(value) !== '') {
                    url.searchParams.append(key, value);
                }
            });

            return url;
        }

        function setWorking(form, isWorking) {
            const target = form.closest('.el-panel') || form.closest('.card') || form;
            target.classList.toggle('el-filter-working', isWorking);
        }

        function loadUrl(url, targets, workingElement) {
            if (!targets.length) {
                return false;
            }

            const loadingElement = workingElement || document.querySelector(targets[0]);
            loadingElement?.classList.add('el-filter-working');

            fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const refreshed = [];

                    targets.forEach(function (selector) {
                        const current = document.querySelector(selector);
                        const incoming = doc.querySelector(selector);

                        if (current && incoming) {
                            current.innerHTML = incoming.innerHTML;
                            Array.from(current.attributes).forEach(function (attribute) {
                                if (attribute.name.startsWith('data-')) {
                                    current.removeAttribute(attribute.name);
                                }
                            });
                            Array.from(incoming.attributes).forEach(function (attribute) {
                                if (attribute.name.startsWith('data-')) {
                                    current.setAttribute(attribute.name, attribute.value);
                                }
                            });
                            refreshed.push(current);
                        }
                    });

                    window.history.pushState({}, '', url.toString());

                    if (window.jQuery && window.jQuery.fn.select2) {
                        refreshed.forEach(function (root) {
                            window.jQuery(root).find('.select2').each(function () {
                                const $select = window.jQuery(this);
                                if ($select.data('select2')) {
                                    $select.select2('destroy');
                                }
                                $select.select2({ width: '100%' });
                            });
                        });
                    }

                    document.dispatchEvent(new CustomEvent('examelite:ajax-filtered', { detail: { url } }));
                })
                .catch(() => {
                    window.location.href = url.toString();
                })
                .finally(() => {
                    loadingElement?.classList.remove('el-filter-working');
                    clearWorking();
                });

            return true;
        }

        function refresh(form) {
            const targets = (form.dataset.elAjaxTarget || '').split(',').map(s => s.trim()).filter(Boolean);

            if (!targets.length) {
                return false;
            }

            const url = formUrl(form);
            setWorking(form, true);

            return loadUrl(url, targets, form.closest('.el-panel') || form.closest('.card') || form);
        }

        document.addEventListener('submit', function (event) {
            const form = event.target.closest('form[data-el-ajax-filter]');
            if (!form || String(form.method).toLowerCase() !== 'get') {
                return;
            }

            event.preventDefault();
            refresh(form);
        });

        document.addEventListener('input', function (event) {
            const field = event.target.closest('[data-el-autofilter]');
            if (!field || field.tagName === 'SELECT') {
                return;
            }

            const form = field.form;
            if (!form || !form.matches('[data-el-ajax-filter]')) {
                return;
            }

            clearTimeout(timers.get(form));
            timers.set(form, setTimeout(() => refresh(form), Number(field.dataset.elAutofilterDelay || 500)));
        });

        document.addEventListener('change', function (event) {
            const field = event.target.closest('[data-el-autofilter]');
            if (!field) {
                return;
            }

            const form = field.form;
            if (form && form.matches('[data-el-ajax-filter]')) {
                clearTimeout(timers.get(form));
                refresh(form);
            }
        });

        window.addEventListener('pageshow', () => clearWorking());
        document.addEventListener('examelite:ajax-filtered', () => clearWorking());
        document.addEventListener('select2:close', () => setTimeout(clearWorking, 50));
        document.addEventListener('select2:select', () => setTimeout(clearWorking, 50));

        return { refresh, loadUrl, clearWorking };
    })();
</script>

{{-- 2. Vendor Scripts --}}
<script src="{{ URL::asset('build/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ URL::asset('build/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ URL::asset('build/libs/node-waves/waves.min.js') }}"></script>
<script src="{{ URL::asset('build/libs/feather-icons/feather.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/plugins/lord-icon-2.1.0.js') }}"></script>

{{-- 3. Third Party Libraries --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

{{-- 4. TinyMCE --}}
@hasSection('rich-editor')
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.9/tinymce.min.js"></script>
@endif

{{-- 5. Main App JS --}}
<script src="{{ URL::asset('build/js/app.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('student-topnav-hamburger-icon')
            || document.getElementById('topnav-hamburger-icon');
        const html = document.documentElement;
        const sidebarStorageKey = toggle?.id === 'student-topnav-hamburger-icon'
            ? 'examelite-student-sidebar-size'
            : 'examelite-admin-sidebar-size';

        const sidebarIsExpanded = function () {
            if (window.innerWidth < 992) {
                const menu = document.querySelector('.app-menu');
                return document.body.classList.contains('vertical-sidebar-enable')
                    || (menu && parseFloat(window.getComputedStyle(menu).marginLeft) === 0);
            }

            return html.getAttribute('data-sidebar-size') !== 'sm';
        };

        const updateToggle = function () {
            if (!toggle) return;

            const expanded = sidebarIsExpanded();
            const action = expanded ? 'Collapse sidebar' : 'Expand sidebar';
            const icon = toggle.querySelector('.sidebar-toggle-icon');
            const text = toggle.querySelector('.sidebar-toggle-text');

            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.setAttribute('aria-label', action);
            toggle.setAttribute('title', action);
            if (text) text.textContent = action;
            if (icon) {
                icon.classList.toggle('ri-menu-fold-line', expanded);
                icon.classList.toggle('ri-menu-unfold-line', !expanded);
            }
        };

        if (toggle) {
            toggle.addEventListener('click', function () {
                if (window.innerWidth < 992) return;

                const nextSize = html.getAttribute('data-sidebar-size') === 'sm' ? 'lg' : 'sm';
                html.setAttribute('data-sidebar-size', nextSize);
                try {
                    localStorage.setItem(sidebarStorageKey, nextSize);
                    sessionStorage.setItem('data-sidebar-size', nextSize);
                } catch (error) {
                    // Keep the toggle functional even when storage is unavailable.
                }
                updateToggle();
                window.setTimeout(function () {
                    window.dispatchEvent(new Event('resize'));
                }, 120);
            });

            document.addEventListener('examelite:sidebar-toggled', updateToggle);
            window.addEventListener('resize', updateToggle);
            updateToggle();
        }

        const themeToggle = document.querySelector('.light-dark-mode');
        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                window.setTimeout(function () {
                    const selectedTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
                    try {
                        localStorage.setItem('examelite-admin-theme', selectedTheme);
                        sessionStorage.setItem('data-bs-theme', selectedTheme);
                    } catch (error) {
                        // The selected mode still applies for the current page.
                    }
                }, 0);
            });
        }
    });
</script>

{{-- 
    ✅ 6. GLOBAL EDITOR INIT WITH FOCUS TRACKING
    Ye script ab track karegi ki user ne last kis editor par click kiya.
--}}
<script>
    // Global variable to track the last focused editor
    window.lastFocusedEditor = null;

    document.addEventListener("DOMContentLoaded", function() {
        if (typeof tinymce !== 'undefined' && document.querySelectorAll('.myeditorinstance').length > 0) {
            tinymce.init({
                selector: 'textarea.myeditorinstance',
                height: 300,
                menubar: false,
                branding: false,
                
                // ✅ ADDED THIS LINE: Tells TinyMCE NOT to strip any custom math HTML tags
                extended_valid_elements: '*[*]',

                // ✅ Add Focus Tracker Here
                setup: function (editor) {
                    editor.on('focus', function () {
                        // Jab user kisi box pe click karega, hum use save kar lenge
                        window.lastFocusedEditor = editor;
                    });
                },

                external_plugins: {'mathjax': '/build/libs/tinymce-mathjax-main/plugin.js'},
                plugins: [
                    "advlist", "anchor", "autolink", "charmap", "code", "codesample", "fullscreen",
                    "help", "image", "insertdatetime", "link", "lists", "media",
                    "preview", "searchreplace", "table", "visualblocks",
                ],
                toolbar: "code | undo redo | styleselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | mathjax",
                mathjax: {
                    lib: 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js',
                    config: '/build/libs/tinymce-mathjax-main/config.js',
                    symbols: { start: '\\(', end: '\\)', inline: true }
                },
                paste_data_images: true,
                images_upload_url: '{{ route("upload-image") }}',
                images_upload_handler: function (blobInfo, success, failure) {
                    var xhr, formData;
                    xhr = new XMLHttpRequest();
                    xhr.withCredentials = false;
                    xhr.open('POST', '{{ route("upload-image") }}');
                    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
                    xhr.onload = function() {
                        var json;
                        if (xhr.status != 200) { failure('HTTP Error: ' + xhr.status); return; }
                        json = JSON.parse(xhr.responseText);
                        success(json.location);
                    };
                    formData = new FormData();
                    formData.append('file', blobInfo.blob(), blobInfo.filename());
                    xhr.send(formData);
                }
            });
        }
    });
</script>

<script>
window.alert = function (message) {
    return Swal.fire({ icon: 'warning', title: 'Notice', text: String(message ?? '') });
};

document.addEventListener('submit', function (event) {
    const form = event.target.closest('form');
    const trigger = event.submitter?.matches('[data-swal-confirm]') ? event.submitter : form?.matches('[data-swal-confirm]') ? form : null;
    if (!form || !trigger || form.dataset.swalConfirmed === '1') return;
    event.preventDefault();
    Swal.fire({
        icon: trigger.dataset.swalIcon || 'warning',
        title: trigger.dataset.swalTitle || 'Please confirm',
        text: trigger.dataset.swalConfirm,
        showCancelButton: true,
        confirmButtonText: trigger.dataset.swalButton || 'Continue',
        confirmButtonColor: trigger.dataset.swalColor || '#0f766e'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        form.dataset.swalConfirmed = '1';
        if (typeof form.requestSubmit === 'function') form.requestSubmit(event.submitter || undefined);
        else form.submit();
    });
});

document.addEventListener('click', function (event) {
    const button = event.target.closest('button[data-swal-confirm]');
    if (!button || button.form || button.dataset.swalConfirmed === '1') return;
    event.preventDefault();
    Swal.fire({
        icon: button.dataset.swalIcon || 'warning',
        title: button.dataset.swalTitle || 'Please confirm',
        text: button.dataset.swalConfirm,
        showCancelButton: true,
        confirmButtonText: button.dataset.swalButton || 'Continue',
        confirmButtonColor: button.dataset.swalColor || '#0f766e'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        button.dataset.swalConfirmed = '1';
        button.click();
    });
});
</script>
@yield('script')
@stack('scripts')
<script src="{{ URL::asset('js/admin-table-enhancements.js') }}?v={{ filemtime(public_path('js/admin-table-enhancements.js')) }}"></script>

<script>
document.addEventListener('submit', function(event) {
    if (event.target.id !== 'logout-form') return;
    try {
        Object.keys(localStorage).filter(key => key.startsWith('t4l:draft:v1:')).forEach(key => localStorage.removeItem(key));
    } catch (error) { /* Sign-out must work when browser storage is unavailable. */ }
}, true);
</script>
</body>
</html>
<!-- ========================================================================= -->
<!-- AI CONTENT GENERATOR - COMPLETE WORKING VERSION                           -->
<!-- ========================================================================= -->

@if ($showAdminAiContentTools)
<style>
.ai-fixed-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: var(--el-primary, var(--vz-primary, #0f766e));
    color: white;
    border: none;
    border-radius: 60px;
    padding: 14px 28px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    z-index: 999999;
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    display: none;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.ai-fixed-btn:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 12px 28px rgba(15, 118, 110, 0.28);
    background: var(--el-primary, var(--vz-primary, #0f766e));
    filter: brightness(0.94);
}

.ai-badge-small {
    background: rgba(255,255,255,0.25);
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 10px;
    font-weight: 500;
}

.ai-swal-content {
    color: var(--el-muted, #7b8497);
    text-align: left;
}

.ai-swal-title {
    color: var(--el-primary, var(--vz-primary, #0f766e));
    font-size: 22px;
    font-weight: 700;
}

.ai-swal-help {
    color: var(--el-muted, #7b8497);
    font-size: 13px;
    margin-bottom: 10px;
}

.ai-swal-label {
    color: var(--el-primary, var(--vz-primary, #0f766e));
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.ai-swal-textarea {
    border: 1px solid var(--el-border, #d7e2df) !important;
    border-radius: var(--el-radius, 4px) !important;
    font-size: 14px !important;
    padding: 12px !important;
    width: 100% !important;
}

.ai-swal-target {
    background: var(--el-primary-soft, rgba(15, 118, 110, 0.12));
    border: 1px solid var(--el-border, #d7e2df);
    border-radius: var(--el-radius, 4px);
    color: var(--el-primary, var(--vz-primary, #0f766e));
    font-size: 12px;
    margin-top: 12px;
    padding: 10px;
    text-align: center;
}
</style>

@php $aiContentEnabled = \App\Support\SaasAccess::featureEnabled('ai_content_generation'); @endphp
<button id="aiGenBtn" class="ai-fixed-btn {{ $aiContentEnabled ? '' : 'js-plan-feature-locked' }}" data-plan-feature="{{ $aiContentEnabled ? '' : 'ai_content_generation' }}">
    <span style="font-size: 20px;">✨</span>
    <span>AI Content Generator</span>
    <span class="ai-badge-small" id="aiProviderLabel">AI</span>
</button>

<script>
// =========================================================================
// AI CONTENT GENERATOR - FULL FUNCTIONALITY
// =========================================================================

let currentActiveEditor = null;
let explicitAiEditor = null;

// Detect which editor is currently active
function detectActiveEditor() {
    // A directly focused textarea must win over stale rich-editor state.
    let active = document.activeElement;
    if (active && active.tagName === 'TEXTAREA') {
        return { type: 'textarea', instance: active };
    }

    // Check TinyMCE only when its editor actually has focus.
    if (typeof tinymce !== 'undefined') {
        let editor = tinymce.activeEditor;
        const hasFocus = editor && (typeof editor.hasFocus === 'function' ? editor.hasFocus() : editor.focused === true);
        if (editor && editor.selection && hasFocus) {
            return { type: 'tinymce', instance: editor };
        }
    }
    // Check CKEditor
    if (typeof CKEDITOR !== 'undefined') {
        for (let id in CKEDITOR.instances) {
            let editor = CKEDITOR.instances[id];
            if (editor.focusManager && editor.focusManager.hasFocus) {
                return { type: 'ckeditor', instance: editor };
            }
        }
    }
    return null;
}

// Insert content at cursor position
function insertAtCursor(content, editor) {
    if (editor.type === 'tinymce') {
        editor.instance.insertContent(content);
        return true;
    }
    else if (editor.type === 'ckeditor') {
        editor.instance.insertHtml(content);
        return true;
    }
    else if (editor.type === 'textarea') {
        const el = editor.instance;
        if (!el || !el.isConnected || el.disabled || el.readOnly) return false;

        const generated = String(content || '').trim();
        if (!generated) return false;

        const start = Number.isInteger(el.selectionStart) ? el.selectionStart : el.value.length;
        const end = Number.isInteger(el.selectionEnd) ? el.selectionEnd : start;
        el.setRangeText(generated, start, end, 'end');
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));

        return el.value.includes(generated);
    }
    return false;
}

// Track when user clicks on any editor
$(document).on('click focus', 'textarea, .tox-edit-area, .cke_editable, .tox-tinymce, .cke', function() {
    setTimeout(function() {
        currentActiveEditor = detectActiveEditor();
        if (currentActiveEditor) {
            $('#aiGenBtn').css('transform', 'scale(1.05)');
            setTimeout(function() { $('#aiGenBtn').css('transform', ''); }, 200);
        }
    }, 100);
});

// Main button click handler

document.getElementById('aiGenBtn').addEventListener('click', function(e) {
    e.preventDefault();

    if (this.classList.contains('js-plan-feature-locked')) {
        Swal.fire({
            icon: 'info',
            title: 'Not included in your plan',
            text: 'Please contact the platform administrator to enable this feature for your organization.',
            confirmButtonText: 'OK'
        });
        return;
    }

    currentActiveEditor = explicitAiEditor || detectActiveEditor();
    explicitAiEditor = null;

    if (!currentActiveEditor) {
        Swal.fire('Select a text box', 'Please click inside the text box where content should be inserted.', 'info');
        return;
    }

    const targetEditor = currentActiveEditor;
    const activeEl = targetEditor.instance;
    const form = activeEl && activeEl.closest ? activeEl.closest('form') : document.querySelector('form');

    const readField = function(names) {
        if (!form) return '';
        for (const name of names) {
            const field = form.querySelector(`[name="${name}"]`);
            if (field && field.value) return field.value;
        }
        return '';
    };

    const title = readField(['name', 'title', 'group_name', 'short_title']);
    const description = readField(['description', 'instruction', 'syllabus']);
    const fieldName = activeEl && activeEl.getAttribute ? (activeEl.getAttribute('name') || '') : '';
    const label = activeEl && activeEl.id
        ? document.querySelector(`label[for="${activeEl.id}"]`)
        : null;

    const fieldLabel = label ? label.textContent.trim() : fieldName;
    const pageTitle = document.title || 'ExamElite admin page';

    let autoPrompt = `Write helpful content for ExamElite. Field: ${fieldLabel}. Page: ${pageTitle}.`;

    if (title) {
        autoPrompt += ` Title: ${title}.`;
    }

    if (description && description !== activeEl.value) {
        autoPrompt += ` Existing context: ${description}.`;
    }

    if (fieldName === 'description') {
        autoPrompt += ' Create one SEO-friendly paragraph of 70 to 110 words for an exam package or page description.';
    } else if (fieldName === 'instruction') {
        autoPrompt += ' Create a clear student-facing exam introduction/instruction paragraph of 70 to 110 words.';
    } else if (fieldName === 'syllabus') {
        autoPrompt += ' Create a concise syllabus overview paragraph. Do not invent detailed chapters if not provided.';
    } else {
        autoPrompt += ' Create one useful paragraph of 70 to 110 words.';
    }

    autoPrompt += ' Use natural language. Prefer exam, mock test, previous year questions, PYQ, online practice where relevant. Do not overstuff keywords. Do not mention course unless unavoidable. Return only content, no heading.';

    const styles = getComputedStyle(document.documentElement);
    const primaryColor = (styles.getPropertyValue('--el-primary') || styles.getPropertyValue('--vz-primary') || '#0f766e').trim();
    const secondaryColor = (styles.getPropertyValue('--el-secondary') || styles.getPropertyValue('--vz-warning') || '#f59e0b').trim();

    Swal.fire({
        title: '<span class="ai-swal-title">AI Content Generator</span>',
        html: `
            <div class="ai-swal-content">
                <p class="ai-swal-help">
                    AI will generate content automatically from this form. Optional: add extra instruction below.
                </p>

                <label class="ai-swal-label">Extra instruction (optional)</label>
                <textarea id="aiPromptInput" class="swal2-textarea ai-swal-textarea" rows="3" placeholder="Example: Make it suitable for GATE aspirants"></textarea>

                <div class="ai-swal-target">
                    <span>Content will be inserted into: <strong>${fieldLabel || 'selected text box'}</strong></span>
                </div>
            </div>
        `,
        width: '560px',
        showCancelButton: true,
        confirmButtonText: 'Generate & Insert',
        cancelButtonText: 'Cancel',
        confirmButtonColor: primaryColor,
        cancelButtonColor: secondaryColor,
        preConfirm: function() {
            const extra = document.getElementById('aiPromptInput').value.trim();
            return {
                prompt: extra ? autoPrompt + ' Extra instruction: ' + extra : autoPrompt,
                context: [
                    title ? 'Title: ' + title : '',
                    description ? 'Context: ' + description : '',
                    'URL: ' + window.location.href
                ].filter(Boolean).join("\n")
            };
        }
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Generating...',
                text: 'AI is creating your content. Please wait.',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            $.ajax({
                url: '{{ route("ai.content.generate") }}',
                type: 'POST',
                data: {
                    prompt: result.value.prompt,
                    context: result.value.context,
                    _token: '{{ csrf_token() }}'
                },
                dataType: 'json',
                timeout: 45000,
                success: function(response) {
                    if (response.success) {
                        if (insertAtCursor(response.content, targetEditor)) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Content Inserted',
                                html: '<div style="text-align:center;"><strong>AI content has been inserted</strong></div>',
                                timer: 1800,
                                showConfirmButton: false
                            });

                            $('#aiProviderLabel').text('AI');
                        } else {
                            Swal.fire('Error', 'Could not insert content. Please click inside editor again.', 'error');
                        }
                    } else {
                        Swal.fire('Error', response.message || 'Failed to generate content', 'error');
                    }
                },
                error: function(xhr) {
                    var errorMsg = 'Something went wrong. Please try again.';
                    if (xhr.statusText === 'timeout') {
                        errorMsg = 'The AI provider timed out. Please try again or select another provider in AI Settings.';
                    }
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    Swal.fire('Error', errorMsg, 'error');
                }
            });
        }
    });
});

</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('aiGenBtn')) return;

    document.querySelectorAll('textarea').forEach(function (textarea) {
        if (textarea.closest('.modal')) return;
        if (textarea.closest('.swal2-container')) return;
        if (textarea.closest('[data-disable-ai-inline]')) return;
        if (textarea.id === 'aiPromptInput') return;

        const fieldName = textarea.getAttribute('name') || '';
        const seoFields = [
            'meta_title',
            'meta_description',
            'meta_keywords',
            'canonical_url',
            'og_title',
            'og_description',
            'og_image',
            'robots_meta',
            'seo_schema'
        ];

        if (seoFields.includes(fieldName)) return;
        if (textarea.closest('.seo-settings-card')) return;
        if (textarea.dataset.aiInlineReady === '1') return;
        textarea.dataset.aiInlineReady = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex justify-content-end mb-1';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm el-btn-primary ai-inline-content-btn';
        btn.innerHTML = 'Generate with AI';

        btn.addEventListener('click', function () {
            textarea.focus();

            explicitAiEditor = {
                type: 'textarea',
                instance: textarea
            };

            document.getElementById('aiGenBtn').click();
        });

        wrapper.appendChild(btn);
        textarea.parentNode.insertBefore(wrapper, textarea);
    });
});
</script>

@endif

