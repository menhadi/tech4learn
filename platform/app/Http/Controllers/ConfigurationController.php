<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Configuration;
use App\Models\Organization;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Support\SaasAccess;
use App\Support\Tenant;

class ConfigurationController extends Controller
{
    public function editLogoFavicon()
    {
        $configuration = getConfiguration();
        return view('configurations.ologo', compact('configuration'));
    }

    public function updateLogoFavicon(Request $request)
    {
        if (env('DEMO_MODE', false)) {
            return redirect()->route('configurations.logo-favicon')->with('error', 'This action is disabled for demo users.');
        }

        $request->validate([
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:1024',
            'favicon' => 'nullable|image|mimes:ico,png|max:512|dimensions:width=32,height=32',
            'remove_logo' => 'nullable|boolean',
            'remove_favicon' => 'nullable|boolean',
            'brand_fallback_name' => 'nullable|string|max:255',
            'brand_fallback_tagline' => 'nullable|string|max:255',
            'brand_fallback_icon' => 'nullable|string|max:100',
        ]);

        $configuration = getConfiguration();

        if ($request->boolean('remove_logo') && optional($configuration)->logo) {
            Storage::disk('public')->delete($configuration->logo);
            $configuration->logo = null;
        }

        if ($request->boolean('remove_favicon') && optional($configuration)->favicon) {
            Storage::disk('public')->delete($configuration->favicon);
            $configuration->favicon = null;
        }

        if ($request->hasFile('logo')) {
            if (optional($configuration)->logo) {
                Storage::disk('public')->delete($configuration->logo);
            }
            $configuration->logo = $request->file('logo')->store('configurations', 'public');
        }

        if ($request->hasFile('favicon')) {
            if (optional($configuration)->favicon) {
                Storage::disk('public')->delete($configuration->favicon);
            }
            $configuration->favicon = $request->file('favicon')->store('configurations', 'public');
        }

        $configuration->brand_fallback_name = $request->input('brand_fallback_name') ?: null;
        $configuration->brand_fallback_tagline = $request->input('brand_fallback_tagline') ?: null;
        $configuration->brand_fallback_icon = $request->input('brand_fallback_icon') ?: null;

        if (SaasAccess::featureEnabled('custom_theme')) {
            $configuration->theme_primary_color = $request->input('theme_primary_color', '#0f766e');
            $configuration->theme_secondary_color = $request->input('theme_secondary_color', '#f59e0b');
            $configuration->theme_header_bg = $request->input('theme_header_bg', '#ffffff');
            $configuration->theme_header_text = $request->input('theme_header_text', '#0f172a');
            $configuration->theme_footer_bg = $request->input('theme_footer_bg', '#0f172a');
            $configuration->theme_footer_text = $request->input('theme_footer_text', '#cbd5e1');
            $configuration->theme_body_bg = $request->input('theme_body_bg', '#ffffff');
            $configuration->theme_heading_color = $request->input('theme_heading_color', '#0f172a');
            $configuration->theme_button_text = $request->input('theme_button_text', '#ffffff');
        }

        $configuration->save();
        audit_log('configuration.logo_favicon_updated', $configuration);

        Cache::forget('app.configuration');
        cache()->forget('ef_configuration_site');

        return redirect()->route('configurations.logo-favicon')->with('success', 'Logo and Favicon updated successfully.');
    }

    public function editGeneral()
    {
        $configuration = getConfiguration();
        return view('configurations.general', compact('configuration'));
    }

    public function updateGeneral(Request $request)
    {
        if (env('DEMO_MODE', false)) {
            return redirect()->route('configurations.general')->with('error', 'This action is disabled for demo users.');
        }

        $request->validate([
            'site_name' => 'required|string|max:255',
            'organization_name' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
            'organization_email' => 'required|email|max:255',
            'currency' => 'required',
            'timezone' => 'required|string', // âœ… ADDED THIS: Validation for Timezone
            'allow_guest_exam_attempts' => 'nullable|boolean',
            'student_welcome_email_enabled' => 'nullable|boolean',
            'student_pending_admin_email_enabled' => 'nullable|boolean',
            'student_manual_activation_email_enabled' => 'nullable|boolean',
            'require_student_registration_number' => 'nullable|boolean',
            'student_founder_followup_enabled' => 'nullable|boolean',
            'guest_contact_email_enabled' => 'nullable|boolean',
            'secondary_menu_bg_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_menu_text_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_menu_hover_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_menu_top_border_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_menu_bottom_border_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'show_package_tag_filters' => 'nullable|boolean',
            'meta_title' => 'required|string|max:255',
            'meta_keyword' => 'required|string|max:255',
            'meta_content' => 'required|string',
            'powered_by' => 'required|string|max:255',
            'powered_link' => 'required|url|max:255',

            'organization_phone' => 'required|string|max:255',
            'organization_alternate_phone' => 'required|string|max:255',
            'organization_address' => 'required|string|max:255',
            'organization_tagline' => 'required|string|max:255',
        ]);

        $configuration = getConfiguration();

        $configuration->name = $request->site_name;
        $configuration->organization_name = $request->organization_name;
        $configuration->domain_name = $request->domain;
        $configuration->email = $request->organization_email;
        $configuration->meta_title = $request->meta_title;
        $configuration->meta_keyword = $request->meta_keyword;
        $configuration->meta_content = $request->meta_content;
        $configuration->powered_by = $request->powered_by;
        $configuration->powered_link = $request->powered_link;

        $configuration->organization_phone = $request->organization_phone;
        $configuration->organization_alternate_phone = $request->organization_alternate_phone;
        $configuration->organization_address = $request->organization_address;
        $configuration->organization_tagline = $request->organization_tagline;
        $configuration->currency = $request->currency;
        
        // âœ… ADDED THIS: Saving Timezone
        $configuration->timezone = $request->timezone;
        $configuration->allow_guest_exam_attempts = $request->boolean('allow_guest_exam_attempts');
        $configuration->student_welcome_email_enabled = $request->boolean('student_welcome_email_enabled');
        $configuration->student_pending_admin_email_enabled = $request->boolean('student_pending_admin_email_enabled');
        $configuration->student_manual_activation_email_enabled = $request->boolean('student_manual_activation_email_enabled');
        $configuration->require_student_registration_number = $request->boolean('require_student_registration_number');
        $configuration->student_founder_followup_enabled = $request->boolean('student_founder_followup_enabled');
        $configuration->guest_contact_email_enabled = $request->boolean('guest_contact_email_enabled');
        $configuration->secondary_menu_bg_color = $request->input('secondary_menu_bg_color') ?: null;
        $configuration->secondary_menu_text_color = $request->input('secondary_menu_text_color') ?: null;
        $configuration->secondary_menu_hover_color = $request->input('secondary_menu_hover_color') ?: null;
        $configuration->secondary_menu_top_border_color = $request->input('secondary_menu_top_border_color') ?: null;
        $configuration->secondary_menu_bottom_border_color = $request->input('secondary_menu_bottom_border_color') ?: null;
        $configuration->show_package_tag_filters = $request->boolean('show_package_tag_filters');
        if (SaasAccess::featureEnabled('custom_theme')) {
            $configuration->theme_primary_color = $request->input('theme_primary_color', '#0f766e');
            $configuration->theme_secondary_color = $request->input('theme_secondary_color', '#f59e0b');
            $configuration->theme_header_bg = $request->input('theme_header_bg', '#ffffff');
            $configuration->theme_header_text = $request->input('theme_header_text', '#0f172a');
            $configuration->theme_footer_bg = $request->input('theme_footer_bg', '#0f172a');
            $configuration->theme_footer_text = $request->input('theme_footer_text', '#cbd5e1');
            $configuration->theme_body_bg = $request->input('theme_body_bg', '#ffffff');
            $configuration->theme_heading_color = $request->input('theme_heading_color', '#0f172a');
            $configuration->theme_button_text = $request->input('theme_button_text', '#ffffff');
        }


        $configuration->save();
        audit_log('configuration.general_updated', $configuration);
        
        // Clear cache so new timezone takes effect immediately
        cache()->forget('ef_configuration_site');
        
        return redirect()->route('configurations.general')->with('success', 'Configuration updated successfully.');
    }

    public function editAiSettings()
    {
        SaasAccess::abortIfFeatureDisabled('ai_settings');

        $configuration = $this->configurationForTenantWrite();

        return view('configurations.ai', compact('configuration'));
    }

    public function updateAiSettings(Request $request)
    {
        if (env('DEMO_MODE', false)) {
            return redirect()->route('configurations.ai')->with('error', 'This action is disabled for demo users.');
        }

        SaasAccess::abortIfFeatureDisabled('ai_settings');

        $request->validate([
            'ai_provider_priority' => 'required|array|size:4',
            'ai_provider_priority.*' => 'required|string|distinct|in:google,openai,deepseek,anthropic',
            'ai_task_priorities' => 'required|array',
            'ai_task_priorities.translation' => 'required|array|size:4',
            'ai_task_priorities.academic_review' => 'required|array|size:4',
            'ai_task_priorities.source_text_audit' => 'required|array|size:4',
            'ai_task_priorities.source_text_audit.*' => 'required|string|distinct|in:google,openai,deepseek,anthropic',
            'ai_task_priorities.image_audit' => 'required|array|size:4',
            'ai_task_priorities.image_audit.*' => 'required|string|distinct|in:google,openai,deepseek,anthropic',
            'ai_task_priorities.academic_review.*' => 'required|string|distinct|in:google,openai,deepseek,anthropic',
            'ai_task_priorities.answer_explanation' => 'required|array|size:4',
            'ai_task_priorities.answer_explanation.*' => 'required|string|distinct|in:google,openai,deepseek,anthropic',
            'ai_task_priorities.translation.*' => 'required|string|distinct|in:google,openai,deepseek,anthropic',
            'ai_task_priorities.question_generation' => 'required|array|size:4',
            'ai_task_priorities.question_generation.*' => 'required|string|distinct|in:google,openai,deepseek,anthropic',
            'ai_task_priorities.question_regeneration' => 'required|array|size:4',
            'ai_task_priorities.question_regeneration.*' => 'required|string|distinct|in:google,openai,deepseek,anthropic',
            'ai_task_priorities.content_seo' => 'required|array|size:4',
            'ai_task_priorities.content_seo.*' => 'required|string|distinct|in:google,openai,deepseek,anthropic',
            'ai_task_priorities.subjective_assessment' => 'required|array|size:4',
            'ai_task_priorities.subjective_assessment.*' => 'required|string|distinct|in:google,openai,deepseek,anthropic',
            'google_gemini_api_key' => 'nullable|string',
            'openai_api_key' => 'nullable|string',
            'deepseek_api_key' => 'nullable|string',
            'anthropic_api_key' => 'nullable|string',
            'google_gemini_model' => 'nullable|string|max:100',
            'openai_model' => 'nullable|string|max:100',
            'deepseek_model' => 'nullable|string|max:100',
            'deepseek_vision_model' => 'nullable|string|max:100',
            'anthropic_model' => 'nullable|string|max:100',
            'google_gemini_model_custom' => 'nullable|string|max:100|required_if:google_gemini_model,__custom__',
            'openai_model_custom' => 'nullable|string|max:100|required_if:openai_model,__custom__',
            'deepseek_model_custom' => 'nullable|string|max:100|required_if:deepseek_model,__custom__',
            'deepseek_vision_model_custom' => 'nullable|string|max:100|required_if:deepseek_vision_model,__custom__',
            'anthropic_model_custom' => 'nullable|string|max:100|required_if:anthropic_model,__custom__',
            'mathpix_enabled' => 'nullable|boolean',
            'mathpix_app_id' => 'nullable|string|max:255',
            'mathpix_app_key' => 'nullable|string|max:2000',
            'mathpix_min_confidence' => 'nullable|numeric|min:0|max:100',
            'image_cleanup_enabled' => 'nullable|boolean',
            'image_cleanup_provider' => 'required|in:openai,google',
            'image_cleanup_openai_model' => 'required|string|max:120',
            'image_cleanup_google_model' => 'required|string|max:120',
            'image_cleanup_quality' => 'required|in:low,medium,high',
            'image_cleanup_branding_enabled' => 'nullable|boolean',
            'image_cleanup_branding_mode' => 'required|in:logo,text',
            'image_cleanup_watermark_text' => 'nullable|string|max:120',
            'image_cleanup_watermark_opacity' => 'required|integer|min:3|max:25',
            'ai_regeneration_quality_prompt' => 'nullable|string|max:20000',
            'ai_regeneration_mcq_prompt' => 'nullable|string|max:20000',
            'ai_regeneration_true_false_prompt' => 'nullable|string|max:20000',
            'ai_regeneration_fill_blank_prompt' => 'nullable|string|max:20000',
            'ai_regeneration_subjective_prompt' => 'nullable|string|max:20000',
            'ai_regeneration_nat_prompt' => 'nullable|string|max:20000',
            'study_card_question_rotation_enabled' => 'nullable|boolean',

        ]);

        $configuration = $this->configurationForTenantWrite();
        
        $configuration->ai_provider_priority = array_values($request->input('ai_provider_priority', []));
        $configuration->ai_task_priorities = collect($request->input('ai_task_priorities', []))
            ->map(fn ($priority) => array_values((array) $priority))
            ->all();
        $configuration->google_gemini_api_key = $request->google_gemini_api_key;
        $configuration->openai_api_key = $request->openai_api_key;
        $configuration->deepseek_api_key = $request->deepseek_api_key;
        $configuration->anthropic_api_key = $request->anthropic_api_key;
        $configuration->google_gemini_model = $request->google_gemini_model === '__custom__' ? $request->google_gemini_model_custom : ($request->google_gemini_model ?: 'gemini-1.5-flash');
        $configuration->openai_model = $request->openai_model === '__custom__' ? $request->openai_model_custom : ($request->openai_model ?: 'gpt-4o');
        $configuration->deepseek_model = $request->deepseek_model === '__custom__' ? $request->deepseek_model_custom : ($request->deepseek_model ?: 'deepseek-chat');
        $configuration->deepseek_vision_model = $request->deepseek_vision_model === '__custom__'
            ? $request->deepseek_vision_model_custom
            : ($request->deepseek_vision_model ?: 'deepseek-v4-flash-vision-exp');
        $configuration->anthropic_model = $request->anthropic_model === '__custom__' ? $request->anthropic_model_custom : ($request->anthropic_model ?: 'claude-sonnet-4-5');
        $configuration->mathpix_enabled = $request->boolean('mathpix_enabled');
        $configuration->mathpix_app_id = $request->input('mathpix_app_id');
        $configuration->mathpix_app_key = $request->input('mathpix_app_key');
        $configuration->mathpix_min_confidence = $request->input('mathpix_min_confidence', 70);
        $configuration->image_cleanup_enabled = $request->boolean('image_cleanup_enabled');
        $configuration->image_cleanup_provider = $request->input('image_cleanup_provider', 'openai');
        $configuration->image_cleanup_openai_model = $request->input('image_cleanup_openai_model', 'gpt-image-2');
        $configuration->image_cleanup_google_model = $request->input('image_cleanup_google_model', 'gemini-3.1-flash-image');
        $configuration->image_cleanup_quality = $request->input('image_cleanup_quality', 'medium');
        $configuration->image_cleanup_branding_enabled = $request->boolean('image_cleanup_branding_enabled');
        $configuration->image_cleanup_branding_mode = $request->input('image_cleanup_branding_mode', 'logo');
        $configuration->image_cleanup_watermark_text = trim((string) $request->input('image_cleanup_watermark_text')) ?: null;
        $configuration->image_cleanup_watermark_opacity = $request->integer('image_cleanup_watermark_opacity', 8);
        foreach (['ai_regeneration_quality_prompt', 'ai_regeneration_mcq_prompt', 'ai_regeneration_true_false_prompt', 'ai_regeneration_fill_blank_prompt', 'ai_regeneration_subjective_prompt', 'ai_regeneration_nat_prompt'] as $field) {
            $configuration->{$field} = $request->input($field);
        }
        $configuration->study_card_question_rotation_enabled = $request->boolean('study_card_question_rotation_enabled');
        $configuration->save();
        audit_log('configuration.ai_updated', $configuration);
        cache()->forget('ef_configuration_site');

        return redirect()->route('configurations.ai')->with('success', 'AI settings updated successfully.');
    }

    private function configurationForTenantWrite(): Configuration
    {
        if (! class_exists(Tenant::class) || ! \Schema::hasColumn('configurations', 'organization_id')) {
            return getConfiguration();
        }

        $tenantId = Tenant::hostId(request()->getHost()) ?: Tenant::id();

        if (! $tenantId) {
            return getConfiguration();
        }

        $configuration = Configuration::where('organization_id', $tenantId)->first();

        if ($configuration) {
            return $configuration;
        }

        $fallback = getConfiguration();
        $attributes = $fallback->getAttributes();
        unset($attributes['id'], $attributes['organization_id'], $attributes['created_at'], $attributes['updated_at']);

        $configuration = new Configuration();
        $configuration->forceFill($attributes);
        $configuration->organization_id = $tenantId;
        $configuration->save();

        return $configuration;
    }


    public function editOrganizationWebsiteSettings(Organization $organization)
    {
        $configuration = Configuration::where('organization_id', $organization->id)->firstOrFail();

        return view('configurations.website', [
            'configuration' => $configuration,
            'organization' => $organization,
            'formAction' => route('saas.organizations.website.update', $organization),
            'backUrl' => route('saas.index'),
        ]);
    }

    public function updateOrganizationWebsiteSettings(Request $request, Organization $organization)
    {
        $configuration = Configuration::where('organization_id', $organization->id)->firstOrFail();

        $this->saveWebsiteSettings($request, $configuration);
        audit_log('configuration.website_updated', $configuration, ['organization_id' => $organization->id]);

        cache()->forget('app.configuration');
        cache()->forget('ef_configuration_site');

        return redirect()->route('saas.index')->with('success', $organization->name . ' website settings updated successfully.');
    }

    public function editWebsiteSettings()
    {
        $configuration = getConfiguration();

        return view('configurations.website', compact('configuration'));
    }

    public function updateWebsiteSettings(Request $request)
    {
        $configuration = getConfiguration();

        $this->saveWebsiteSettings($request, $configuration);
        audit_log('configuration.website_updated', $configuration);

        cache()->forget('app.configuration');
        cache()->forget('ef_configuration_site');

        return redirect()->back()->with('success', 'Website settings updated successfully.');
    }

    private function saveWebsiteSettings(Request $request, Configuration $configuration): void
    {
        $validated = $request->validate([
            'homepage_show_hero' => 'nullable|boolean',
            'subcategories_enabled' => 'nullable|boolean',
            'homepage_show_featured_packages' => 'nullable|boolean',
            'homepage_show_top_performers' => 'nullable|boolean',
            'homepage_show_testimonials' => 'nullable|boolean',
            'homepage_show_counters' => 'nullable|boolean',
            'homepage_show_features' => 'nullable|boolean',
            'quick_quiz_show_hero' => 'nullable|boolean',
            'quick_quiz_prompt_frequency' => 'required|in:never,session,daily,three_days,weekly',
            'partner_popup_enabled' => 'nullable|boolean',
            'partner_popup_scope' => 'required|in:all_pages,homepage',
            'partner_popup_delay_seconds' => 'required|integer|min:0|max:600',
            'partner_popup_repeat_days' => 'required|integer|min:0|max:365',
            'partner_popup_eyebrow' => 'nullable|string|max:80',
            'partner_popup_title' => 'required|string|max:120',
            'partner_popup_message' => 'required|string|max:500',
            'partner_popup_button_label' => 'required|string|max:60',
            'partner_popup_url' => 'required|url:http,https|max:1000',
            'partner_popup_position' => 'required|in:bottom_right,bottom_left,center',
            'partner_popup_accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'partner_popup_background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'partner_popup_icon' => 'required|in:school,book,heart,community',
            'partner_popup_open_new_tab' => 'nullable|boolean',
            'homepage_hero_title' => 'nullable|string|max:255',
            'homepage_hero_phrases' => 'nullable|string|max:2000',
            'homepage_hero_description' => 'nullable|string|max:1000',
            'homepage_search_placeholder' => 'nullable|string|max:255',
            'homepage_search_button_text' => 'nullable|string|max:100',
            'homepage_search_empty_text' => 'nullable|string|max:255',
            'homepage_hero_desktop_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'homepage_hero_mobile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'remove_homepage_hero_desktop_image' => 'nullable|boolean',
            'remove_homepage_hero_mobile_image' => 'nullable|boolean',
            'homepage_hero_overlay' => 'nullable|integer|min:0|max:85',
            'homepage_hero_alignment' => 'nullable|in:left,center',
            'social_share_enabled' => 'nullable|boolean',
            'social_share_platforms' => 'nullable|array',
            'social_share_platforms.*' => 'in:native,facebook,x,linkedin,whatsapp,telegram,reddit,pinterest,email,copy',
            'social_profiles' => 'nullable|array',
            'social_profiles.*' => 'nullable|url:http,https|max:1000',
        ]);

        foreach (['desktop', 'mobile'] as $size) {
            $field = "homepage_hero_{$size}_image";
            if ($request->boolean("remove_{$field}") && $configuration->{$field}) {
                Storage::disk('public')->delete($configuration->{$field});
                $configuration->{$field} = null;
            }
            if ($request->hasFile($field)) {
                if ($configuration->{$field}) Storage::disk('public')->delete($configuration->{$field});
                $configuration->{$field} = $request->file($field)->store('configurations/hero', 'public');
            }
        }
        $configuration->save();
        $partnerPopupSettings = [
            'enabled' => $request->boolean('partner_popup_enabled'),
            'scope' => $validated['partner_popup_scope'],
            'delay_seconds' => (int) $validated['partner_popup_delay_seconds'],
            'repeat_days' => (int) $validated['partner_popup_repeat_days'],
            'eyebrow' => trim((string) ($validated['partner_popup_eyebrow'] ?? '')),
            'title' => trim($validated['partner_popup_title']),
            'message' => trim($validated['partner_popup_message']),
            'button_label' => trim($validated['partner_popup_button_label']),
            'url' => trim($validated['partner_popup_url']),
            'position' => $validated['partner_popup_position'],
            'accent_color' => strtolower($validated['partner_popup_accent_color']),
            'background_color' => strtolower($validated['partner_popup_background_color']),
            'icon' => $validated['partner_popup_icon'],
            'open_new_tab' => $request->boolean('partner_popup_open_new_tab'),
        ];
        $websiteSettings = [
            'subcategories_enabled' => $request->boolean('subcategories_enabled'),
            'homepage_show_hero' => $request->boolean('homepage_show_hero'),
            'homepage_show_featured_packages' => $request->boolean('homepage_show_featured_packages'),
            'homepage_show_top_performers' => $request->boolean('homepage_show_top_performers'),
            'homepage_show_testimonials' => $request->boolean('homepage_show_testimonials'),
            'homepage_show_counters' => $request->boolean('homepage_show_counters'),
            'homepage_show_features' => $request->boolean('homepage_show_features'),
            'quick_quiz_show_hero' => $request->boolean('quick_quiz_show_hero'),
            'quick_quiz_prompt_frequency' => $validated['quick_quiz_prompt_frequency'] ?? 'daily',
            'partner_popup_settings' => $partnerPopupSettings,
            'homepage_hero_title' => ($validated['homepage_hero_title'] ?? null) ?: null,
            'homepage_hero_phrases' => ($validated['homepage_hero_phrases'] ?? null) ?: null,
            'homepage_hero_description' => ($validated['homepage_hero_description'] ?? null) ?: null,
            'homepage_search_placeholder' => ($validated['homepage_search_placeholder'] ?? null) ?: null,
            'homepage_search_button_text' => ($validated['homepage_search_button_text'] ?? null) ?: null,
            'homepage_search_empty_text' => ($validated['homepage_search_empty_text'] ?? null) ?: null,
            'homepage_hero_overlay' => $validated['homepage_hero_overlay'] ?? null,
            'homepage_hero_alignment' => ($validated['homepage_hero_alignment'] ?? null) ?: null,
            'social_sharing_settings' => [
                'enabled' => $request->boolean('social_share_enabled'),
                'platforms' => array_values(array_unique($validated['social_share_platforms'] ?? [])),
                'profiles' => collect($validated['social_profiles'] ?? [])
                    ->map(fn ($url) => trim((string) $url))
                    ->filter()
                    ->all(),
            ],
        ];

        if (! Schema::hasColumn('configurations', 'subcategories_enabled')) {
            unset($websiteSettings['subcategories_enabled']);
        }
        if (! Schema::hasColumn('configurations', 'quick_quiz_show_hero')) {
            unset($websiteSettings['quick_quiz_show_hero']);
        }
        if (! Schema::hasColumn('configurations', 'quick_quiz_prompt_frequency')) {
            unset($websiteSettings['quick_quiz_prompt_frequency']);
        }
        if (! Schema::hasColumn('configurations', 'partner_popup_settings')) {
            unset($websiteSettings['partner_popup_settings']);
        }
        if (! Schema::hasColumn('configurations', 'social_sharing_settings')) {
            unset($websiteSettings['social_sharing_settings']);
        }

        $configuration->update($websiteSettings);
    }


}
