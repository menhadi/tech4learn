<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens; // ✅ Kept your existing import

class Student extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'address',
        'phone',
        'guardian_phone',
        'enroll',
        'photo',
        'status',
        'is_demo',
        'demo_batch_id',
        'demo_created_by',
        'demo_generated_at',
        'reg_code',
        'reg_status',
        'expiry_days',
        'renewal_date',
        'presetcode',
        'last_login',
        'otp',
        'otp_expires_at',
        'language',
        'google_id',
        'provider',
        'email_verified_at',
        'phone_verified_at',
        'otp_channel',
        'welcome_email_sent_at',
        'pending_admin_notified_at',
        'admin_activated_at',
        'admin_activation_email_sent_at',
        'founder_followup_sent_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'welcome_email_sent_at' => 'datetime',
        'pending_admin_notified_at' => 'datetime',
        'admin_activated_at' => 'datetime',
        'admin_activation_email_sent_at' => 'datetime',
        'founder_followup_sent_at' => 'datetime',
        'is_demo' => 'boolean',
        'demo_generated_at' => 'datetime',
        'last_login' => 'datetime',
        'renewal_date' => 'date',
    ];

    // =======================================================
    // >> YEH SECTION HUMNE ADD KIYA HAI (Photo URL ke liye) <<
    // =======================================================
    /**
     * Hamesha 'photo_url' naam ka ek naya field API response mein add karega
     */
    protected $appends = ['photo_url'];

    /**
     * 'photo_url' field ki value calculate karta hai.
     */
    // public function getPhotoUrlAttribute()
    // {
    //     // if ($this->photo) {
    //     //     // Agar photo path database mein hai, to poora URL return karo
    //     //     return Storage::disk('public')->url($this->photo);
    //     // }

    //     if ($this->photo) {

    //         // If Google image URL
    //         if (filter_var($this->photo, FILTER_VALIDATE_URL)) {
    //             return $this->photo;
    //         }

    //         // Local uploaded image
    //         return Storage::disk('public')->url($this->photo);
    //     }

    //     // Agar photo nahi hai, to default placeholder (Gravatar ya UI Avatar) return karo
    //     return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=random';
    // }
    // =======================================================

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'student_groups', 'student_id', 'group_id');
    }

    // ==========================================
    // ✅ THIS WAS MISSING - ADDED NOW
    // ==========================================
    public function examResults()
    {
        return $this->hasMany(ExamResult::class, 'student_id', 'id');
    }

    public function flashcardProgress()
    {
        return $this->hasMany(StudentFlashcardProgress::class, 'student_id', 'id');
    }

    public function flashcardPoints()
    {
        return $this->hasMany(StudentFlashcardPoint::class, 'student_id', 'id');
    }
    // ==========================================

    public function generateOtp()
    {
        $this->otp = rand(100000, 999999);
        $this->otp_expires_at = Carbon::now()->addMinutes(5);
        $this->save();
    }

    public function verifyOtp($otp)
    {
        return $this->otp === $otp && Carbon::now()->lessThanOrEqualTo($this->otp_expires_at);
    }

    public function getPhotoUrlAttribute()
    {
        if ($this->provider === 'google' && $this->photo) {
            return $this->photo;
        }

        if ($this->photo) {
            return asset('storage/'.$this->photo);
        }

        return asset('storage/images/default-avatar.jpg');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function demoCreator()
    {
        return $this->belongsTo(User::class, 'demo_created_by');
    }
}
