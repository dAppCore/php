<?php

declare(strict_types=1);

namespace Core\Mod\Trees\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when the tree reserve falls below threshold.
 *
 * Levels:
 * - warning: Below 50 trees
 * - critical: Below 10 trees
 * - depleted: 0 trees remaining
 */
class LowTreeReserveNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $level,
        public int $remaining
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->level) {
            'depleted' => 'Tree Reserve Depleted - Immediate Action Required',
            'critical' => 'Tree Reserve Critical - Action Required',
            default => 'Tree Reserve Running Low - Plan Next Donation',
        };

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($this->getGreeting())
            ->line($this->getMainMessage())
            ->line("Current reserve: {$this->remaining} trees");

        if ($this->level === 'depleted') {
            $message->line('New tree confirmations will be queued until the reserve is replenished.');
        }

        $message->line('To replenish the reserve, make a donation at:')
            ->action('Donate to Trees for the Future', 'https://donate.trees.org/-/NPMMSVUP?member=SWZTDDWH')
            ->line('After donating, run `php artisan trees:reserve:add [count]` to update the reserve.');

        return $message;
    }

    /**
     * Get the greeting based on level.
     */
    protected function getGreeting(): string
    {
        return match ($this->level) {
            'depleted' => 'Urgent: Tree Reserve Empty',
            'critical' => 'Warning: Tree Reserve Critical',
            default => 'Notice: Tree Reserve Low',
        };
    }

    /**
     * Get the main message based on level.
     */
    protected function getMainMessage(): string
    {
        return match ($this->level) {
            'depleted' => 'The tree reserve has been depleted. No more trees can be confirmed until a donation is made.',
            'critical' => 'The tree reserve is critically low and will be depleted soon.',
            default => 'The tree reserve is running low. Plan your next donation to Trees for the Future.',
        };
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'level' => $this->level,
            'remaining' => $this->remaining,
        ];
    }
}
