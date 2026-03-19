<?php

namespace modules\notifications\services;

use craft\db\Query;
use craft\helpers\App;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use modules\notifications\records\NotificationLog;
use yii\base\Component;

class Notifications extends Component
{
    public static function canSendNotification(int $userId, int $classEntryId, string $period = 'day', int $limit = 1): bool
    {
        $dateCondition = match ($period) {
            'day' => 'DATE(dateCreated) = CURDATE()',
            'week' => 'YEARWEEK(dateCreated, 1) = YEARWEEK(NOW(), 1)',
            default => 'DATE(dateCreated) = CURDATE()'
        };

        $count = (new Query())
            ->from('{{%notification_logs}}')
            ->where(['userId' => $userId, 'classEntryId' => $classEntryId])
            ->andWhere($dateCondition)
            ->count();

        return $count < $limit;
    }

    public static function sendPushNotifications(array $subscriptions, array $payload): array
    {
        $notifications = array_map(fn ($s) => [
            'subscription' => Subscription::create([
                'endpoint' => $s['endpoint'],
                'keys' => [
                    'p256dh' => $s['p256dhKey'],
                    'auth' => $s['authKey'],
                ],
            ]),
            'payload' => json_encode($payload),
        ], $subscriptions);

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => App::env('VAPID_SUBJECT'),
                'publicKey' => App::env('VAPID_PUBLIC_KEY'),
                'privateKey' => App::env('VAPID_PRIVATE_KEY'),
            ],
        ]);

        foreach ($notifications as $n) {
            $webPush->queueNotification(
                $n['subscription'],
                $n['payload'],
            );
        }

        $results = [];
        foreach ($webPush->flush() as $report) {
            $results[] = [
                'endpoint' => $report->getRequest()->getUri()->__toString(),
                'success' => $report->isSuccess(),
                'reason' => $report->getReason(),
            ];
        }

        return $results;
    }

    public static function logNotification(int $userId, int $classEntryId, int $pushCount, int $emailCount): bool
    {
        $log = new NotificationLog();
        $log->userId = $userId;
        $log->classEntryId = $classEntryId;
        $log->pushCount = $pushCount;
        $log->emailCount = $emailCount;

        return $log->save();
    }

    public static function sendEmailNotifications(array $emails, array $payload): array
    {
        $view = \Craft::$app->getView();
        $mailer = \Craft::$app->getMailer();
        $subject = $payload['subject'] ?? 'Class Updates from MDS';
        $body = $view->renderTemplate('_emails/class-notification', $payload, $view::TEMPLATE_MODE_SITE);

        $results = [];
        foreach ($emails as $email) {
            try {
                $success = $mailer->compose()
                    ->setTo($email)
                    ->setSubject($subject)
                    ->setHtmlBody($body)
                    ->send();

                $results[] = [
                    'email' => $email,
                    'success' => $success,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'email' => $email,
                    'success' => false,
                ];
            }
        }

        return $results;
    }

    public static function notificationSentAfterDate(int $classEntryId, \DateTime $date): bool
    {
        return NotificationLog::find()
            ->where(['classEntryId' => $classEntryId])
            ->andWhere(['>=', 'dateCreated', $date->format('Y-m-d H:i:s')])
            ->exists();
    }
}
