<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CustomEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $emailHtml;

    public function __construct(string $subjectLine, string $emailHtml)
    {
        $this->subjectLine = $subjectLine;
        $this->emailHtml   = $emailHtml;
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('send_email.custom') // your existing blade
            ->with([
                'emailTemplate' => $this->emailHtml, // {!! $emailTemplate !!}
                'subject'       => $this->subjectLine, // for <title>{{ $subject }}</title>
            ]);
    }
}
