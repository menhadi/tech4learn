<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'organization_name',
        'domain_name',
        'email',
        'meta_title',
        'meta_keyword',
        'meta_content',
        'timezone',
        'author',
        'sms_notification',
        'email_notification',
        'guest_login',
        'front_end',
        'slides',
        'translate',
        'paid_exam',
        'leader_board',
        'math_editor',
        'certificate',
        'contact',
        'email_contact',
        'logo',
        'signature',
        'favicon',
        'date_format',
        'exam_expiry',
        'exam_feedback',
        'tolerance_count',
        'powered_by',
        'powered_link',
        'theme_primary_color',
        'theme_secondary_color',
        'theme_header_bg',
        'theme_header_text',
        'theme_footer_bg',
        'theme_footer_text',
        'theme_body_bg',
        'theme_heading_color',
        'theme_button_text',
        'secondary_menu_bg_color',
        'secondary_menu_text_color',
        'secondary_menu_hover_color',
        'secondary_menu_top_border_color',
        'secondary_menu_bottom_border_color',
        'show_package_tag_filters',
        'student_otp_channel',
        'sms_provider',
        'whatsapp_provider',
        'default_country_code',
        'messaging_credentials',
        'messaging_settings',
        'allow_guest_exam_attempts',
        'subcategories_enabled',
        'student_welcome_email_enabled',
        'student_pending_admin_email_enabled',
        'student_manual_activation_email_enabled',
        'require_student_registration_number',
        'student_founder_followup_enabled',
        'guest_contact_email_enabled',
        'homepage_show_hero',
        'homepage_show_featured_packages',
        'homepage_show_top_performers',
        'homepage_show_testimonials',
        'homepage_show_counters',
        'homepage_show_features',
        'quick_quiz_show_hero',
        'quick_quiz_prompt_frequency',
        'partner_popup_settings',
        'social_sharing_settings',
        'brand_fallback_name',
        'brand_fallback_tagline',
        'brand_fallback_icon',
        'homepage_hero_title',
        'homepage_hero_phrases',
        'homepage_hero_description',
        'homepage_search_placeholder',
        'homepage_search_button_text',
        'homepage_search_empty_text',
        'homepage_hero_desktop_image',
        'homepage_hero_mobile_image',
        'homepage_hero_overlay',
        'homepage_hero_alignment',
        'ai_provider',
        'google_gemini_api_key',
        'openai_api_key',
        'deepseek_api_key',
        'anthropic_api_key',
        'google_gemini_model',
        'openai_model',
        'deepseek_model',
        'deepseek_vision_model',
        'anthropic_model',
        'mathpix_enabled',
        'mathpix_app_id',
        'mathpix_app_key',
        'mathpix_min_confidence',
        'image_cleanup_enabled',
        'image_cleanup_provider',
        'image_cleanup_openai_model',
        'image_cleanup_google_model',
        'image_cleanup_quality',
        'image_cleanup_branding_enabled',
        'image_cleanup_branding_mode',
        'image_cleanup_watermark_text',
        'image_cleanup_watermark_opacity',
        'study_card_question_rotation_enabled',
        'ai_regeneration_quality_prompt',
        'ai_regeneration_mcq_prompt',
        'ai_regeneration_true_false_prompt',
        'ai_regeneration_fill_blank_prompt',
        'ai_regeneration_subjective_prompt',
        'ai_regeneration_nat_prompt',
        'source_extractor_mappings',
        'pyp_settings',
    ];

    protected $casts = [
        'subcategories_enabled' => 'boolean',
        'show_package_tag_filters' => 'boolean',
        'quick_quiz_show_hero' => 'boolean',
        'mathpix_enabled' => 'boolean',
        'mathpix_min_confidence' => 'float',
        'mathpix_app_key' => 'encrypted',
        'image_cleanup_enabled' => 'boolean',
        'image_cleanup_branding_enabled' => 'boolean',
        'image_cleanup_watermark_opacity' => 'integer',
        'ai_provider_priority' => 'array',
        'ai_task_priorities' => 'array',
        'source_extractor_mappings' => 'array',
        'pyp_settings' => 'array',
        'messaging_credentials' => 'encrypted:array',
        'messaging_settings' => 'array',
        'partner_popup_settings' => 'array',
        'social_sharing_settings' => 'array',
    ];

    protected static function booted()
    {
        static::saved(function () {
            cache()->forget('app.configuration');
            cache()->forget('ef_configuration_site');
        });

        static::deleted(function () {
            cache()->forget('app.configuration');
            cache()->forget('ef_configuration_site');
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
