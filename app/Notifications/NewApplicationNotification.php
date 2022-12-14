<?php

namespace App\Notifications;

use App\LoanApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class NewApplicationNotification extends Notification
{
    use Queueable;

    /**
     * @var LoanApplication
     */
    private $loanApplication;

    public function __construct($loanApplication)
    {
        $this->loanApplication = $loanApplication;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->line('New loan application has been submitted')
                    ->action('See Application', route('admin.loan-applications.show', $this->loanApplication))
                    ->line('Thank you for using our application!');
    }
}
