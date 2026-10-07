@extends('layouts.master')

@section('title')
    Website Settings
@endsection

@section('content')

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Homepage Content</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @php
                $homepageLinks = [
                    ['label' => 'Homepage Content', 'text' => 'Manage benefits, statistics and student reviews in one flexible editor.', 'route' => 'homepage-content.index', 'icon' => 'ri-layout-masonry-line'],
                    ['label' => 'Website Pages', 'text' => 'Edit About, Contact and custom pages.', 'route' => 'websitepages.index', 'icon' => 'ri-pages-line'],
                    ['label' => 'PYP Pages & Analysis', 'text' => 'Control generated year, subject, topic and trend pages.', 'route' => 'pyp-pages.index', 'icon' => 'ri-line-chart-line'],
                ];
            @endphp

            @foreach($homepageLinks as $link)
                @if(Route::has($link['route']))
                    <div class="col-xl-4 col-md-6">
                        <a href="{{ route($link['route']) }}" class="text-decoration-none">
                            <div class="border rounded p-3 h-100 bg-light">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="avatar-title rounded bg-primary-subtle text-primary fs-4" style="width:42px;height:42px;">
                                        <i class="{{ $link['icon'] }}"></i>
                                    </span>
                                    <div>
                                        <h6 class="mb-1 text-dark">{{ $link['label'] }}</h6>
                                        <p class="text-muted mb-0 small">{{ $link['text'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>

    @component('components.breadcrumb')
        @slot('li_1')
            Settings
        @endslot
        @slot('title')
            Website Settings
        @endslot
    @endcomponent

    <div class="row">
        <div class="col-12 col-xl-10">
            <div class="card">
                <div class="card-header border-0">
                    <h4 class="card-title mb-1">Homepage Sections</h4>
                    <p class="text-muted mb-0">Choose which sections are visible on the public homepage.</p>
                        @isset($organization)
                            <div class="mt-2 text-muted">Editing website settings for <strong>{{ $organization->name }}</strong></div>
                            <a href="{{ $backUrl ?? route('saas.index') }}" class="btn btn-sm btn-light mt-3">
                                <i class="ri-arrow-left-line align-bottom me-1"></i> Back to SaaS
                            </a>
                        @endisset
                </div>

                <form method="POST" action="{{ $formAction ?? route('configurations.website.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        <div class="border rounded p-3 mb-4 bg-light">
                            <h5 class="mb-1">Category Structure</h5>
                            <p class="text-muted small">Control whether the optional subcategory level appears throughout admin, student, and public pages.</p>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="subcategories_enabled" name="subcategories_enabled" value="1" {{ old('subcategories_enabled', $configuration->subcategories_enabled ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="subcategories_enabled">Enable subcategories</label>
                            </div>
                            <div class="form-text">Disabling this hides subcategory menus, selectors, filters, and public discovery links. Existing assignments are preserved.</div>
                        </div>

                        <div class="border rounded p-3 mb-4 bg-light">
                            <h5 class="mb-1">Homepage Hero Text</h5>
                            <p class="text-muted small">Optional. Leave any field empty to use the built-in default shown in its placeholder.</p>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Main Heading</label><input class="form-control" name="homepage_hero_title" value="{{ old('homepage_hero_title', $configuration->homepage_hero_title) }}" placeholder="Find Your Exam."></div>
                                <div class="col-md-6"><label class="form-label">Rotating Phrases</label><input class="form-control" name="homepage_hero_phrases" value="{{ old('homepage_hero_phrases', $configuration->homepage_hero_phrases) }}" placeholder="Start Practice Fast | Open PYP Papers | Take Mock Tests"><div class="form-text">Separate phrases with |</div></div>
                                <div class="col-12"><label class="form-label">Supporting Text</label><textarea class="form-control" rows="2" name="homepage_hero_description" placeholder="Search mock tests, PYP, exams by name, subject or groups.">{{ old('homepage_hero_description', $configuration->homepage_hero_description) }}</textarea></div>
                                <div class="col-md-5"><label class="form-label">Search Placeholder</label><input class="form-control" name="homepage_search_placeholder" value="{{ old('homepage_search_placeholder', $configuration->homepage_search_placeholder) }}" placeholder="Search JEE, NEET, CAT, GATE, SSC..."></div>
                                <div class="col-md-3"><label class="form-label">Search Button</label><input class="form-control" name="homepage_search_button_text" value="{{ old('homepage_search_button_text', $configuration->homepage_search_button_text) }}" placeholder="Search Exams"></div>
                                <div class="col-md-4"><label class="form-label">Empty Search Helper</label><input class="form-control" name="homepage_search_empty_text" value="{{ old('homepage_search_empty_text', $configuration->homepage_search_empty_text) }}" placeholder="Type an exam, subject, or group name."></div>
                            </div>
                        </div>
                        <div class="border rounded p-3 mb-4">
                            <h5 class="mb-1">Hero Background</h5>
                            <p class="text-muted small">Optional. With no image, the current theme background remains active.</p>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Desktop Image</label><input type="file" class="form-control" name="homepage_hero_desktop_image" accept="image/png,image/jpeg,image/webp">@if($configuration->homepage_hero_desktop_image)<div class="mt-2"><img src="{{ asset('storage/'.$configuration->homepage_hero_desktop_image) }}" alt="Desktop hero" style="max-width:180px;max-height:90px;object-fit:cover"><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_homepage_hero_desktop_image" value="1"> <span class="form-check-label">Remove desktop image</span></label></div>@endif</div>
                                <div class="col-md-6"><label class="form-label">Mobile Image</label><input type="file" class="form-control" name="homepage_hero_mobile_image" accept="image/png,image/jpeg,image/webp">@if($configuration->homepage_hero_mobile_image)<div class="mt-2"><img src="{{ asset('storage/'.$configuration->homepage_hero_mobile_image) }}" alt="Mobile hero" style="max-width:100px;max-height:120px;object-fit:cover"><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_homepage_hero_mobile_image" value="1"> <span class="form-check-label">Remove mobile image</span></label></div>@endif</div>
                                <div class="col-md-6"><label class="form-label">Overlay Strength: <span id="heroOverlayValue">{{ old('homepage_hero_overlay', $configuration->homepage_hero_overlay ?? 35) }}%</span></label><input type="range" class="form-range" min="0" max="85" step="5" name="homepage_hero_overlay" value="{{ old('homepage_hero_overlay', $configuration->homepage_hero_overlay ?? 35) }}" oninput="document.getElementById('heroOverlayValue').textContent=this.value+'%'"></div>
                                <div class="col-md-6"><label class="form-label">Text Alignment</label><select class="form-select" name="homepage_hero_alignment"><option value="center" {{ old('homepage_hero_alignment', $configuration->homepage_hero_alignment ?: 'center') === 'center' ? 'selected' : '' }}>Center</option><option value="left" {{ old('homepage_hero_alignment', $configuration->homepage_hero_alignment) === 'left' ? 'selected' : '' }}>Left</option></select></div>
                            </div>
                        </div>
                        <div class="border rounded p-3 mb-4 bg-light">
                            <h5 class="mb-1">Quick Quiz Discovery</h5>
                            <p class="text-muted small">Control how public visitors discover Quick Quiz. Signed-in students always have their dedicated Quiz page.</p>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6">
                                    <div class="form-check form-switch border rounded bg-white p-3 ps-5 h-100">
                                        <input type="checkbox" class="form-check-input" id="quick_quiz_show_hero" name="quick_quiz_show_hero" value="1" {{ old('quick_quiz_show_hero', $configuration->quick_quiz_show_hero ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="quick_quiz_show_hero">Show Quick Quiz button in hero</label>
                                        <div class="form-text">Turn this off later to remove the homepage hero button.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="quick_quiz_prompt_frequency">Guest invitation frequency</label>
                                    <select class="form-select" id="quick_quiz_prompt_frequency" name="quick_quiz_prompt_frequency">
                                        @foreach(['never' => 'Never show', 'session' => 'Once per browser session', 'daily' => 'Once daily', 'three_days' => 'Every 3 days', 'weekly' => 'Once weekly'] as $frequency => $label)
                                            <option value="{{ $frequency }}" {{ old('quick_quiz_prompt_frequency', $configuration->quick_quiz_prompt_frequency ?: 'daily') === $frequency ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">The corner invitation appears after 12 seconds and respects this browser-level cooldown.</div>
                                </div>
                            </div>
                        </div>
                        @php
                            $socialDefaults = ['enabled' => true, 'platforms' => ['native', 'facebook', 'x', 'linkedin', 'whatsapp', 'telegram', 'email', 'copy'], 'profiles' => []];
                            $social = array_replace($socialDefaults, is_array($configuration->social_sharing_settings ?? null) ? $configuration->social_sharing_settings : []);
                            $socialPlatforms = [
                                'native' => ['Device share', 'ri-share-forward-line'], 'facebook' => ['Facebook', 'ri-facebook-fill'],
                                'x' => ['X (Twitter)', 'ri-twitter-x-line'], 'linkedin' => ['LinkedIn', 'ri-linkedin-fill'],
                                'whatsapp' => ['WhatsApp', 'ri-whatsapp-line'], 'telegram' => ['Telegram', 'ri-telegram-line'],
                                'reddit' => ['Reddit', 'ri-reddit-line'], 'pinterest' => ['Pinterest', 'ri-pinterest-line'],
                                'email' => ['Email', 'ri-mail-line'], 'copy' => ['Copy link', 'ri-link'],
                            ];
                            $profilePlatforms = ['facebook' => 'Facebook page', 'instagram' => 'Instagram profile', 'x' => 'X profile', 'linkedin' => 'LinkedIn page', 'youtube' => 'YouTube channel', 'telegram' => 'Telegram channel'];
                            $selectedSocialPlatforms = old('social_share_platforms', $social['platforms']);
                        @endphp
                        <div class="border rounded p-3 mb-4">
                            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                                <div><h5 class="mb-1">Social Media & Page Sharing</h5><p class="text-muted small mb-0">Choose the networks visitors can use and add your official social pages to the footer.</p></div>
                                <div class="form-check form-switch"><input type="hidden" name="social_share_enabled" value="0"><input class="form-check-input" type="checkbox" id="social_share_enabled" name="social_share_enabled" value="1" @checked(old('social_share_enabled', $social['enabled']))><label class="form-check-label fw-semibold" for="social_share_enabled">Show public share button</label></div>
                            </div>
                            <label class="form-label fw-semibold">Visitor sharing platforms</label>
                            <div class="row g-2 mb-4">
                                @foreach($socialPlatforms as $key => [$label, $icon])
                                    <div class="col-lg-3 col-md-4 col-6"><label class="border rounded p-2 w-100 bg-light"><input class="form-check-input me-2" type="checkbox" name="social_share_platforms[]" value="{{ $key }}" @checked(in_array($key, $selectedSocialPlatforms ?? [], true))><i class="{{ $icon }} me-1"></i>{{ $label }}</label></div>
                                @endforeach
                            </div>
                            <label class="form-label fw-semibold">Official social profile links</label>
                            <div class="row g-3">
                                @foreach($profilePlatforms as $key => $label)
                                    <div class="col-md-6"><label class="form-label" for="social_profile_{{ $key }}">{{ $label }}</label><input type="url" class="form-control" id="social_profile_{{ $key }}" name="social_profiles[{{ $key }}]" value="{{ old('social_profiles.'.$key, $social['profiles'][$key] ?? '') }}" placeholder="https://"></div>
                                @endforeach
                            </div>
                            <hr class="my-4">
                            <h6>Admin share composer</h6>
                            <p class="text-muted small">Prepare any public Exam Elite link, then open each selected platform's secure share window. You remain in control of the final post.</p>
                            <div class="row g-2">
                                <div class="col-md-5"><input type="url" class="form-control" id="adminShareUrl" placeholder="https://examelite.com/page"></div>
                                <div class="col-md-5"><input type="text" class="form-control" id="adminShareText" placeholder="Post message or title"></div>
                                <div class="col-md-2"><button type="button" class="btn btn-primary w-100" id="adminShareOpen"><i class="ri-share-forward-line me-1"></i>Share</button></div>
                            </div>
                            <div class="form-text" id="adminShareHelp">Click Share to open enabled platforms one at a time.</div>
                        </div>
                        @php
                            $partnerPopupDefaults = [
                                'enabled' => true,
                                'scope' => 'all_pages',
                                'delay_seconds' => 60,
                                'repeat_days' => 7,
                                'eyebrow' => 'Free in-person support',
                                'title' => 'Classroom coaching in Bengaluru',
                                'message' => 'Vector Academy provides free competitive-exam coaching for students from financially disadvantaged backgrounds.',
                                'button_label' => 'Explore Vector Academy',
                                'url' => 'https://vectoracademy.net/',
                                'position' => 'bottom_right',
                                'accent_color' => '#0f7f75',
                                'background_color' => '#ffffff',
                                'icon' => 'school',
                                'open_new_tab' => false,
                            ];
                            $savedPartnerPopup = $configuration->partner_popup_settings ?? [];
                            $partnerPopup = array_replace($partnerPopupDefaults, is_array($savedPartnerPopup) ? $savedPartnerPopup : []);
                        @endphp
                        <div class="border rounded p-3 mb-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h5 class="mb-1">Partner Promotion Popup</h5>
                                    <p class="text-muted small mb-0">Control the message, destination, timing, frequency, and visual style shown on the public website.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="partner_popup_enabled" value="0">
                                    <input type="checkbox" class="form-check-input" id="partner_popup_enabled" name="partner_popup_enabled" value="1" {{ old('partner_popup_enabled', $partnerPopup['enabled']) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="partner_popup_enabled">Popup enabled</label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="partner_popup_scope">Display scope</label>
                                    <select class="form-select" id="partner_popup_scope" name="partner_popup_scope" required>
                                        <option value="all_pages" {{ old('partner_popup_scope', $partnerPopup['scope']) === 'all_pages' ? 'selected' : '' }}>All public pages</option>
                                        <option value="homepage" {{ old('partner_popup_scope', $partnerPopup['scope']) === 'homepage' ? 'selected' : '' }}>Homepage only</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="partner_popup_delay_seconds">Show after</label>
                                    <div class="input-group"><input type="number" class="form-control" id="partner_popup_delay_seconds" name="partner_popup_delay_seconds" min="0" max="600" value="{{ old('partner_popup_delay_seconds', $partnerPopup['delay_seconds']) }}" required><span class="input-group-text">seconds</span></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="partner_popup_repeat_days">Show again after</label>
                                    <div class="input-group"><input type="number" class="form-control" id="partner_popup_repeat_days" name="partner_popup_repeat_days" min="0" max="365" value="{{ old('partner_popup_repeat_days', $partnerPopup['repeat_days']) }}" required><span class="input-group-text">days</span></div>
                                    <div class="form-text">Use 0 for once per browser session.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="partner_popup_eyebrow">Small heading</label>
                                    <input type="text" class="form-control" id="partner_popup_eyebrow" name="partner_popup_eyebrow" maxlength="80" value="{{ old('partner_popup_eyebrow', $partnerPopup['eyebrow']) }}">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="partner_popup_title">Main heading</label>
                                    <input type="text" class="form-control" id="partner_popup_title" name="partner_popup_title" maxlength="120" value="{{ old('partner_popup_title', $partnerPopup['title']) }}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="partner_popup_message">Message</label>
                                    <textarea class="form-control" id="partner_popup_message" name="partner_popup_message" rows="3" maxlength="500" required>{{ old('partner_popup_message', $partnerPopup['message']) }}</textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="partner_popup_button_label">Button label</label>
                                    <input type="text" class="form-control" id="partner_popup_button_label" name="partner_popup_button_label" maxlength="60" value="{{ old('partner_popup_button_label', $partnerPopup['button_label']) }}" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="partner_popup_url">Destination URL</label>
                                    <input type="url" class="form-control" id="partner_popup_url" name="partner_popup_url" maxlength="1000" value="{{ old('partner_popup_url', $partnerPopup['url']) }}" placeholder="https://example.com/page" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="partner_popup_position">Position</label>
                                    <select class="form-select" id="partner_popup_position" name="partner_popup_position" required>
                                        @foreach(['bottom_right' => 'Bottom right', 'bottom_left' => 'Bottom left', 'center' => 'Centre popup'] as $position => $label)
                                            <option value="{{ $position }}" {{ old('partner_popup_position', $partnerPopup['position']) === $position ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="partner_popup_icon">Icon</label>
                                    <select class="form-select" id="partner_popup_icon" name="partner_popup_icon" required>
                                        @foreach(['school' => 'School', 'book' => 'Book', 'heart' => 'Heart', 'community' => 'Community'] as $icon => $label)
                                            <option value="{{ $icon }}" {{ old('partner_popup_icon', $partnerPopup['icon']) === $icon ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check form-switch border rounded w-100 p-3 ps-5">
                                        <input type="hidden" name="partner_popup_open_new_tab" value="0">
                                        <input type="checkbox" class="form-check-input" id="partner_popup_open_new_tab" name="partner_popup_open_new_tab" value="1" {{ old('partner_popup_open_new_tab', $partnerPopup['open_new_tab']) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="partner_popup_open_new_tab">Open link in new tab</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="partner_popup_accent_color">Accent and button colour</label>
                                    <input type="color" class="form-control form-control-color w-100" id="partner_popup_accent_color" name="partner_popup_accent_color" value="{{ old('partner_popup_accent_color', $partnerPopup['accent_color']) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="partner_popup_background_color">Card background colour</label>
                                    <input type="color" class="form-control form-control-color w-100" id="partner_popup_background_color" name="partner_popup_background_color" value="{{ old('partner_popup_background_color', $partnerPopup['background_color']) }}" required>
                                </div>
                            </div>
                        </div>
                        @php
                            $sections = [
                                'homepage_show_hero' => ['Hero Section', 'Main top section with headline, slider, and primary action.'],
                                'homepage_show_featured_packages' => ['Featured Exam Packages', 'Popular/free/paid exam packages shown on homepage.'],
                                'homepage_show_top_performers' => ['Top Performers', 'Group-wise leaderboard and student performance highlights.'],
                                'homepage_show_testimonials' => ['Reviews / Testimonials', 'Student reviews and success feedback.'],
                                'homepage_show_counters' => ['Counters / Stats', 'Numbers like students, exams, packages, attempts.'],
                                'homepage_show_features' => ['Features / About Blocks', 'Marketing feature blocks managed from admin content.'],
                            ];
                        @endphp

                        <div class="row g-3">
                            @foreach($sections as $field => [$label, $help])
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100 bg-light">
                                        <div class="form-check form-switch">
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   id="{{ $field }}"
                                                   name="{{ $field }}"
                                                   value="1"
                                                   {{ old($field, $configuration->{$field} ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label fw-semibold" for="{{ $field }}">
                                                {{ $label }}
                                            </label>
                                        </div>
                                        <p class="text-muted small mb-0 mt-2">{{ $help }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card-footer bg-white text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="ri-save-line align-bottom me-1"></i> Save Website Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
document.getElementById('adminShareOpen')?.addEventListener('click', function () {
    const url = document.getElementById('adminShareUrl').value.trim(), text = document.getElementById('adminShareText').value.trim() || 'Exam Elite';
    const help = document.getElementById('adminShareHelp');
    if (!url) { help.textContent = 'Enter the public page URL first.'; return; }
    try { new URL(url); } catch (_) { help.textContent = 'Enter a valid URL including https://'; return; }
    const u = encodeURIComponent(url), t = encodeURIComponent(text), body = encodeURIComponent(text + ' ' + url);
    const links = {facebook:`https://www.facebook.com/sharer/sharer.php?u=${u}`,x:`https://twitter.com/intent/tweet?text=${t}&url=${u}`,linkedin:`https://www.linkedin.com/sharing/share-offsite/?url=${u}`,whatsapp:`https://api.whatsapp.com/send?text=${body}`,telegram:`https://t.me/share/url?url=${u}&text=${t}`,reddit:`https://www.reddit.com/submit?url=${u}&title=${t}`,pinterest:`https://pinterest.com/pin/create/button/?url=${u}&description=${t}`,email:`mailto:?subject=${t}&body=${body}`};
    const selected = [...document.querySelectorAll('input[name="social_share_platforms[]"]:checked')].map(el => el.value).filter(key => links[key]);
    if (!selected.length) { navigator.clipboard?.writeText(url); help.textContent = 'Link copied. Enable a social platform to open its share window.'; return; }
    selected.forEach((key, index) => setTimeout(() => window.open(links[key], '_blank', 'noopener,noreferrer'), index * 250));
    help.textContent = 'Share windows opened. Your browser may ask permission for multiple pop-ups.';
});
</script>
@endsection
