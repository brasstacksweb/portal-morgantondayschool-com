<?php

namespace modules\notifications\console\controllers;

use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use modules\notifications\NotificationsModule;
use yii\console\ExitCode;

class NotificationsController extends Controller
{
    public function actionSend(): int
    {
        // Get all class entries
        $classes = Entry::find()
            ->section('classes') // Adjust section handle as needed
            ->status('live')
            ->all();

        if (empty($classes)) {
            $this->stdout("No class entries found.\n", Console::FG_RED);

            return ExitCode::OK;
        }

        $notifications = NotificationsModule::getInstance()->get('notifications');
        $subscriptions = NotificationsModule::getInstance()->get('subscriptions');

        $processedCount = 0;
        $sentCount = 0;

        foreach ($classes as $class) {
            $processedCount++;
            $this->stdout("Checking class: {$class->title} (ID: {$class->id})... ");

            // Check if this class has sent a notification since Friday at 5 PM
            $threshold = (new \DateTime())->modify('-2 day')->modify('-2 hour');
            if ($notifications->notificationSentAfterDate($class->id, $threshold)) {
                $this->stdout("✓ Already sent notification this week.\n", Console::FG_GREEN);

                continue;
            }

            $userIds = $subscriptions->getUserIdsForClass($class->id);
            $pushSubscriptions = $subscriptions->getPushSubscriptionsForUsers($userIds);
            $pushCount = count($pushSubscriptions);
            $notificationEmails = array_filter(array_column($class->notificationEmails ?? [], 'email'));
            $emailCount = count($notificationEmails);

            if ($pushCount === 0 && $emailCount === 0) {
                $this->stdout("⚠ No subscribers or email addresses found.\n", Console::FG_YELLOW);

                continue;
            }

            $subject = sprintf('New updates from %s', $class->title);

            $pushResults = [];
            if ($pushCount > 0) {
                $pushResults = $notifications->sendPushNotifications($pushSubscriptions, [
                    'title' => $subject,
                    'body' => 'Click to see what\'s new.',
                    'url' => $class->getUrl(),
                ]);
            }

            $emailResults = [];
            if ($emailCount > 0) {
                $emailResults = $notifications->sendEmailNotifications($notificationEmails, [
                    'subject' => $subject,
                    'message' => null,
                    'classUrl' => $class->getUrl(),
                ]);
            }

            $pushSent = count(array_filter($pushResults, fn ($result) => $result['success']));
            $emailSent = count(array_filter($emailResults, fn ($result) => $result['success']));

            if ($pushSent > 0 || $emailSent > 0) {
                $notifications->logNotification(1, $class->id, $pushSent, $emailSent);
                $sentCount++;
                $this->stdout("✓ Notifications sent to {$pushSent}/{$pushCount} push subscribers and {$emailSent}/{$emailCount} email recipients.\n", Console::FG_GREEN);
            } else {
                $this->stdout("✗ Failed to send notifications.\n", Console::FG_RED);
            }
        }

        $this->stdout("\nWeekly reminder check completed!\n", Console::FG_GREEN);
        $this->stdout("Classes processed: {$processedCount}\n");
        $this->stdout("Reminders sent: {$sentCount}\n");

        return ExitCode::OK;
    }
}
