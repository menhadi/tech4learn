@php
    $safePrimary = preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor ?? '') ? $primaryColor : '#0f766e';
    $safeSecondary = preg_match('/^#[0-9a-fA-F]{6}$/', $secondaryColor ?? '') ? $secondaryColor : '#f59e0b';
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to {{ $siteName }}</title>
</head>
<body style="margin:0;background:#f3f8f7;color:#111827;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:680px;margin:0 auto;padding:26px 14px;">
        <div style="background:#ffffff;border:1px solid #d7e2df;border-radius:14px;overflow:hidden;">
            <div style="padding:28px 28px 24px;background:{{ $safePrimary }};color:#ffffff;">
                <div style="font-size:24px;font-weight:800;line-height:1.25;">Welcome to {{ $siteName }}</div>
                <div style="font-size:14px;margin-top:8px;opacity:.92;">Your exam practice account is ready</div>
            </div>

            <div style="padding:26px 28px;">
                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Hello {{ $student->name }},</p>

                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">
                    Your account is ready. You can now practice exams, mock tests and previous year papers from your student dashboard.
                </p>

                <div style="background:#f8fafc;border:1px solid #d7e2df;border-radius:12px;padding:18px;margin:20px 0;">
                    <div style="font-size:16px;font-weight:800;margin-bottom:12px;color:#111827;">What you can do on {{ $siteName }}</div>
                    <div style="font-size:15px;line-height:1.8;color:#334155;">
                        <div style="margin-bottom:8px;"><strong style="color:{{ $safePrimary }};">Language choice:</strong> Take available exams in the language enabled by your institute.</div>
                        <div style="margin-bottom:8px;"><strong style="color:{{ $safePrimary }};">Practice library:</strong> Access mock tests, PYP and exam packages from your dashboard.</div>
                        <div style="margin-bottom:8px;"><strong style="color:{{ $safePrimary }};">Subjective practice:</strong> Attempt subjective questions when they are available in your exams.</div>
                        <div><strong style="color:{{ $safePrimary }};">Question PDFs:</strong> Download question papers when PDF download is enabled by your institute.</div>
                    </div>
                </div>

                @if($groups->isNotEmpty())
                    <div style="margin:20px 0;">
                        <div style="font-size:15px;font-weight:800;margin-bottom:10px;color:#111827;">Your exam group{{ $groups->count() > 1 ? 's' : '' }}</div>
                        @foreach($groups as $group)
                            @php
                                $groupName = $group->group_name;
                                $groupUrl = $coursesUrl;
                            @endphp
                            <a href="{{ $groupUrl }}" style="display:inline-block;margin:0 8px 8px 0;padding:8px 12px;border-radius:999px;background:#eef7f5;color:{{ $safePrimary }};text-decoration:none;font-weight:800;font-size:14px;">
                                {{ $groupName }}
                            </a>
                        @endforeach
                    </div>
                @endif

                <div style="margin:24px 0 10px;">
                    <a href="{{ $myExamsUrl }}" style="display:inline-block;background:{{ $safePrimary }};color:#ffffff;text-decoration:none;font-weight:800;border-radius:8px;padding:13px 20px;margin:0 8px 10px 0;">
                        Open My Exams
                    </a>
                    <a href="{{ $coursesUrl }}" style="display:inline-block;background:{{ $safeSecondary }};color:#ffffff;text-decoration:none;font-weight:800;border-radius:8px;padding:13px 20px;margin:0 0 10px 0;">
                        Browse Courses
                    </a>
                </div>

                <p style="font-size:14px;line-height:1.6;color:#5f6b7a;margin:20px 0 0;">
                    This is a one-time welcome email after your registration. If you did not create this account, you can ignore this message.
                </p>
            </div>

            <div style="padding:16px 24px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:13px;">
                Regards,<br>
                {{ $organizationName }}
            </div>
        </div>
    </div>
</body>
</html>
