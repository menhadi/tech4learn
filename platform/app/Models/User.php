<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// 👇 Spatie permission trait import
use Spatie\Permission\Traits\HasRoles;

// 👇 Group Model Import karein (Ye zaroori hai)
use App\Models\Group;
use App\Models\Ugroup;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles; 

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'ugroup_id',
        'mobile',   // <-- Maine 'mobile' bhi add kiya hai kyunki aapke controller validation me mobile required hai
        'status',
        'is_platform_admin',
        'avatar',   
        'language', 
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', 
        'is_platform_admin' => 'boolean',
        'otp_expires_at' => 'datetime',
    ];

    // 👇 Relationship for Ugroup (Level)
    public function ugroup()
    {
        return $this->belongsTo(Ugroup::class, 'ugroup_id'); 
    }

    // ✅✅✅ YE FUNCTION MISSING THA, ISE ADD KIYA HAI ✅✅✅
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'user_groups', 'user_id', 'group_id');
    }

    public function generateOtp(): string
    {
        $this->otp = (string) random_int(100000, 999999);
        $this->otp_expires_at = Carbon::now()->addMinutes(10);
        $this->save();

        return $this->otp;
    }

    public function verifyOtp(string $otp): bool
    {
        return $this->otp !== null
            && hash_equals((string) $this->otp, trim($otp))
            && $this->otp_expires_at !== null
            && Carbon::now()->lessThanOrEqualTo($this->otp_expires_at);
    }
}
