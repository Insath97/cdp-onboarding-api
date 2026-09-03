<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserCreateMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $userType = isset($this->data['user']['user_type'])
            ? ucfirst($this->data['user']['user_type'])
            : 'User';

        return new Envelope(
            subject: "Welcome to " . config('app.name') . " - Your {$userType} Account Has Been Created"
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Filter out any N/A values
        $filteredData = $this->filterData($this->data);

        return new Content(
            view: 'mails.new-user',
            with: $filteredData
        );
    }

    /**
     * Filter out N/A values and only pass actual data
     */
    private function filterData($data)
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $filtered[$key] = $this->filterData($value);
            } else {
                // Only include if value is not 'N/A', not null, and not empty
                if ($value !== 'N/A' && $value !== null && $value !== '') {
                    $filtered[$key] = $value;
                }
            }
        }

        return $filtered;
    }

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
