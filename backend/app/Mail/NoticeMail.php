<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic notification mail. With MAIL_MAILER=log (the default here) the
 * message is written to storage/logs/laravel.log, simulating email delivery.
 */
class NoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $noticeSubject,
        public string $noticeBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->noticeSubject);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.notice');
    }
}
