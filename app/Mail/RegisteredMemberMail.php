<?php

namespace App\Mail;

use App\Models\RegisteredMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegisteredMemberMail extends Mailable
{
    use Queueable, SerializesModels;

    public $registeredMember;

    /**
     * Create a new message instance.
     */
    public function __construct(RegisteredMember $registeredMember)
    {
        $this->registeredMember = $registeredMember;
    }

    public function build()
    {
        return $this->subject('Thank You for Registering')
                    ->view('emails.registered_member');
    }

    /**
     * Get the message envelope.
     */
    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'Registered Member Mail',
    //     );
    // }

    /**
     * Get the message content definition.
     */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'view.name',
    //     );
    // }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
