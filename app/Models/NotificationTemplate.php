<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'channel',
        'subject',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Replace template placeholders with dynamic values.
     * e.g. "Hello {name}, your order #{order_number} is placed."
     *
     * @param array<string, mixed> $variables
     * @return array{subject: ?string, body: string}
     */
    public function render(array $variables = []): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($variables as $key => $val) {
            $placeholder = '{' . trim((string) $key) . '}';
            $replacement = (string) (is_array($val) ? json_encode($val) : $val);

            if ($subject !== null) {
                $subject = str_replace($placeholder, $replacement, $subject);
            }
            $body = str_replace($placeholder, $replacement, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
