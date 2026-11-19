<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\MailTemplate;
use App\Models\Reminder;

/**
 * AbsentReminderMail
 *
 * Mailable class for sending absence reminder notifications via email.
 * This class handles the construction and rendering of absence reminder
 * email messages based on predefined email templates.
 *
 * @package App\Mail
 */
class AbsentReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The reminder instance
     *
     * @var Reminder
     */
    public $reminder;
    
    /**
     * Create a new message instance.
     *
     * @param Reminder $reminder The reminder instance containing subject and message
     * @return void
     */
    public function __construct(Reminder $reminder)
    {
        $this->queue='emails';
        $this->reminder = $reminder;
    }

    /**
     * Build the message.
     *
     * Retrieves the email template, replaces placeholders with reminder data,
     * and renders the markdown view with the prepared content.
     *
     * @return $this
     */
    public function build()
    {
        $template = MailTemplate::where([['name','absent_message'],['status','active']])->first();

        $mail_content = $template->mail_content;

        $subject =  $this->reminder['subject'];
        
        $mail_content = str_replace(":message",$this->reminder['message'],$mail_content);
          
        return $this->markdown('emails.mailcontent')
                        ->subject($subject)
                        ->with([
                            'content' => $mail_content,
                            ]);
    }
}