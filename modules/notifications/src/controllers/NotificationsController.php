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
        $class = Entry::findOne($entryId);
        $subscriptions = NotificationsModule::getInstance()->get('subscriptions');
        $notifications = NotificationsModule::getInstance()->get('notifications');
        $userIds = $subscriptions->getUserIdsForClass((int) $entryId);
        $pushSubscriptions = $subscriptions->getPushSubscriptionsForUsers($userIds);

        if (count($pushSubscriptions) === 0) {
            return $this->asFailure('No subscriptions found for class.');
        }

        $payload = [
            'title' => sprintf('New updates from %s', $class->title),
            'body' => 'Click to see what\'s new.',
            'url' => $class->getUrl(),
        ];
        $sent = $notifications->sendPushNotifications($pushSubscriptions, $payload);

        if (count($sent) !== count($pushSubscriptions)) {
            return $this->asFailure('Some notifications failed to send.');
        }

        return $this->asSuccess('Notifications sent successfully to '.count($sent).' subscribers.');
    }

    // public function actionUnreadCount(): Response
    // {
    //     $this->requireLogin();

    //     $notifications = NotificationsModule::getInstance()->get('notifications');
    //     $userId = \Craft::$app->getUser()->getId();
    //     $count = $notifications->getUnreadCount($userId);

    //     return $this->asJson(['count' => $count]);
    // }

    // public function actionMarkRead(): Response
    // {
    //     $this->requirePostRequest();
    //     $this->requireLogin();

    //     $request = \Craft::$app->getRequest();
    //     $updateId = $request->getRequiredBodyParam('updateId');

    //     if (!is_numeric($updateId)) {
    //         return $this->asFailure('Invalid update ID');
    //     }

    //     $notifications = NotificationsModule::getInstance()->get('notifications');
    //     $userId = \Craft::$app->getUser()->getId();
    //     $success = $notifications->markAsRead($userId, (int) $updateId);

    //     return $this->asJson(['success' => $success]);
    // }

    // public function actionMarkAllRead(): Response
    // {
    //     $this->requirePostRequest();
    //     $this->requireLogin();

    //     $notifications = NotificationsModule::getInstance()->get('notifications');
    //     $userId = \Craft::$app->getUser()->getId();
    //     $success = $notifications->markAllAsRead($userId);

    //     return $this->asJson(['success' => $success]);
    // }
}
