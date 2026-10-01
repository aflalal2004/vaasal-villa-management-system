<?php

namespace App\Modules\Notifications\Services;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Log;

/**
 * In-app notifications and alerts. A notification is addressed to one user, or broadcast
 * to everyone holding a permission (e.g. "conflicts.manage" for OTA overbooking alerts).
 * Email / WhatsApp delivery hooks go through the same entry point.
 */
class NotificationService
{
    public static function notify(string $type, string $title, ?string $body = null, ?string $url = null, string $level = 'info', ?string $permission = null, ?int $userId = null): AppNotification
    {
        $n = AppNotification::create([
            'user_id' => $userId,
            'permission' => $permission,
            'type' => $type,
            'level' => $level,
            'title' => mb_substr($title, 0, 255),
            'body' => $body ? mb_substr($body, 0, 1000) : null,
            'url' => $url,
        ]);

        Log::channel(config('logging.default'))->info('notification', ['type' => $type, 'title' => $title, 'permission' => $permission]);

        return $n;
    }

    // Convenience wrappers for the platform's standard alerts ---------------------------

    public static function bookingCreated($booking): void
    {
        self::notify('booking.created', "New booking {$booking->reference}", $booking->guest->fullName().' · '.fmt_date($booking->arrival).' → '.fmt_date($booking->departure).' · '.$booking->sourceLabel(),
            route('admin.bookings.show', $booking), 'success', 'bookings.view');
    }

    public static function bookingConflict($conflict): void
    {
        self::notify('booking.conflict', 'OTA booking conflict — action required', "{$conflict->channel->name} {$conflict->external_ref}: {$conflict->reason}",
            route('admin.conflicts.index'), 'danger', 'conflicts.manage');
    }

    public static function lowStock($item): void
    {
        self::notify('inventory.low', "Low stock: {$item->name}", "On hand {$item->current_qty} {$item->unit}, reorder level {$item->reorder_level}.",
            route('pos.inventory.items.index'), 'warning', 'inventory.manage');
    }
}
