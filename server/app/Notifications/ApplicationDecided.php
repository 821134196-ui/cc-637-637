<?php

namespace App\Notifications;

use App\Models\AccessApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationDecided extends Notification
{
    use Queueable;

    public function __construct(
        public AccessApplication $application,
        public bool $approved,
    ) {}

    public function via(object $notifiable): array
    {
        // MAIL_MAILER=log — simulated email, written to storage/logs/laravel.log
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dataset = $this->application->dataset()->first();

        $mail = (new MailMessage)
            ->subject(sprintf('数据访问申请%s: %s', $this->approved ? '已批准' : '被拒绝', $dataset?->title))
            ->line('您的数据访问申请已有结果。')
            ->line('数据集: '.($dataset?->title))
            ->line('结果: '.($this->approved ? '已批准' : '被拒绝'));

        if ($this->application->decision_note) {
            $mail->line('备注: '.$this->application->decision_note);
        }

        return $mail;
    }
}
