<?php

namespace modules\notifications\controllers;

use craft\elements\Entry;
use craft\web\Controller;
use modules\notifications\NotificationsModule;
use yii\web\Response;

class NotificationsController extends Controller
{
    public function actionSend(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = \Craft::$app->getRequest();
        $entryId = $request->getRequiredBodyParam('entryId');
        $period = $request->getRequiredBodyParam('rateLimitPeriod', 'day'); // 'day' or 'week'
        $limit = (int) $request->getRequiredBodyParam('rateLimitCount', 1); // Default: 1 per day
        $message = $request->getBodyParam('message', 'Click to see what\'s new.');
        $currentUser = \Craft::$app->getUser()->getIdentity();

        $class = Entry::findOne($entryId);
        $subscriptions = NotificationsModule::getInstance()->get('subscriptions');
        $notifications = NotificationsModule::getInstance()->get('notifications');

        if (!$notifications->canSendNotification($currentUser->id, (int) $entryId, $period, $limit)) {
            return $this->asFailure("Rate limit exceeded. You can only send {$limit} notification(s) per {$period} for this class.");
        }

        $userIds = $subscriptions->getUserIdsForClass((int) $entryId);
        $pushSubscriptions = $subscriptions->getPushSubscriptionsForUsers($userIds);

        if (count($pushSubscriptions) === 0) {
            return $this->asFailure('No subscriptions found for class.');
        }

        $payload = [
            'title' => sprintf('New updates from %s', $class->title),
            'body' => $message,
            'url' => $class->getUrl(),
        ];
        $sent = $notifications->sendPushNotifications($pushSubscriptions, $payload);

        if (count($sent) !== count($pushSubscriptions)) {
            return $this->asFailure('Some notifications failed to send.');
        }

        $notifications->logNotification($currentUser->id, (int) $entryId, count($sent));

        return $this->asSuccess('Notifications sent successfully to '.count($sent).' subscribers.');
    }
}
