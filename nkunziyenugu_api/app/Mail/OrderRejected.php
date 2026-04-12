<?php

namespace App\Mail;

use App\Models\ShopOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ShopOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Update on Your Order #' . $this->order->id);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order_rejected');
    }
}
