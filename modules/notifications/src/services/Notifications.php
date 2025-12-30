<?php

namespace modules\notifications\services;

use craft\db\Query;
use craft\elements\Entry;
use craft\helpers\App;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use yii\base\Component;

class Notifications extends Component
{
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

}
