<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\DocumentStage;
use App\Models\Notification as NotificationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Document $document,
        protected DocumentStage $stage
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Update Status Dokumen - ' . $this->document->doc_number)
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Status dokumen Anda telah diperbarui.')
            ->line('**Dokumen:** ' . $this->document->title)
            ->line('**Nomor:** ' . $this->document->doc_number)
            ->line('**Tahapan:** ' . $this->stage->stage_name)
            ->line('**Status:** ' . ucfirst($this->stage->status))
            ->action('Lacak Dokumen', url('/track/' . $this->document->tracking_code))
            ->line('Terima kasih telah menggunakan layanan kami.');
    }

    public function toDatabase(object $notifiable): array
    {
        NotificationModel::create([
            'user_id'     => $notifiable->id,
            'document_id' => $this->document->id,
            'type'        => 'document_status_updated',
            'title'       => 'Status Dokumen Diperbarui',
            'message'     => "Dokumen \"{$this->document->title}\" ({$this->document->doc_number}) — Tahapan \"{$this->stage->stage_name}\" telah " . ($this->stage->status === 'completed' ? 'selesai' : 'dimulai') . '.',
            'is_read'     => false,
            'sent_at'     => now(),
        ]);

        return ['message' => 'Document status updated'];
    }
}
