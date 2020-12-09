<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Receipt extends Mailable
{
    use Queueable, SerializesModels;

    public $payload;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        
        return $this->subject('Transaction Receipt | JabssApp Payments')
        ->view('emails.receipt')
        ->attach(storage_path('trxns/' . $this->payload['attachment']), [
            'as' => 'Transaction_receipt_' . $this->payload['ref'] . '.pdf',
            'mime' => 'application/pdf',
        ]);
    }
}
