<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use HasFactory;

    public const TYPE_STUDENT_OTP_SMS = 'student_otp_sms';

    public const TYPE_STUDENT_OTP_WHATSAPP = 'student_otp_whatsapp';

    public static function readyMadeTemplates(): array
    {
        return [
            self::TYPE_STUDENT_OTP_SMS => [
                'name' => 'Student OTP - SMS',
                'description' => '{#otp#} is your verification code for {#siteName#}. It expires in 10 minutes. Do not share it.',
            ],
            self::TYPE_STUDENT_OTP_WHATSAPP => [
                'name' => 'Student OTP - WhatsApp',
                'description' => 'Your {#siteName#} verification code is {#otp#}. It expires in 10 minutes. Do not share it.',
            ],
        ];
    }

    public function isReadyMade(): bool
    {
        return in_array($this->type, [self::TYPE_STUDENT_OTP_SMS, self::TYPE_STUDENT_OTP_WHATSAPP], true);
    }

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'status',
        'type',
        'dlt_template_id',
    ];
}
