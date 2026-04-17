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
        $message = $request->getBodyParam('message', null);
        $currentUser = \Craft::$app->getUser()->getIdentity();

        $class = Entry::findOne($entryId);
        $subscriptions = NotificationsModule::getInstance()->get('subscriptions');
        $notifications = NotificationsModule::getInstance()->get('notifications');

        if (!$notifications->canSendNotification($currentUser->id, (int) $entryId, $period, $limit)) {
            return $this->asFailure("Rate limit exceeded. You can only send {$limit} notification(s) per {$period} for this class.");
        }

        $userIds = $subscriptions->getUserIdsForClass((int) $entryId);
        $pushSubscriptions = $subscriptions->getPushSubscriptionsForUsers($userIds);
        $pushCount = count($pushSubscriptions);
        $notificationEmails = array_filter(array_column($class->notificationEmails, 'email'));
        $emailCount = count($notificationEmails);

        if ($pushCount === 0 && $emailCount === 0) {
            return $this->asFailure('No push subscriptions or email addresses found for class.');
        }

        $subject = sprintf('New Updates from %s', $class->title);

        $pushResults = [];
        if ($pushCount > 0) {
            $pushResults = $notifications->sendPushNotifications($pushSubscriptions, [
                'title' => $subject,
                'body' => $message ?? 'Click to see what\'s new.',
                'url' => $class->getUrl(),
            ]);
        }

        $emailResults = [];
        if ($emailCount > 0) {
            $emailResults = $notifications->sendEmailNotifications($notificationEmails, [
                'subject' => $subject,
                'message' => $message,
                'classUrl' => $class->getUrl(),
            ]);
        }

        $pushSent = count(array_filter($pushResults, fn ($result) => $result['success']));
        $emailSent = count(array_filter($emailResults, fn ($result) => $result['success']));

        if ($pushSent === 0 && $emailSent === 0) {
            return $this->asFailure('Failed to send notifications.');
        }

        $notifications->logNotification($currentUser->id, (int) $entryId, $pushSent, $emailSent);

        return $this->asSuccess("Notifications sent to {$pushSent}/{$pushCount} push subscribers and {$emailSent}/{$emailCount} email recipients.");
    }

    public function actionPreview(): Response
    {
        return $this->renderTemplate('_emails/class-notification', [
            'subject' => 'New Updates from Third Grade',
            'message' => 'Extra details about the update can go here.',
            'classUrl' => '/classes/third-grade',
        ]);
    }

    public function actionSendPushNotifications(): Response
    {
        // Get all class entries
        $classes = Entry::find()
            ->section('classes') // Adjust section handle as needed
            ->status('live')
            ->all();
        $notifications = NotificationsModule::getInstance()->get('notifications');
        $subscriptions = NotificationsModule::getInstance()->get('subscriptions');

        foreach ($classes as $class) {
            $userIds = $subscriptions->getUserIdsForClass($class->id);
            $pushSubscriptions = $subscriptions->getPushSubscriptionsForUsers($userIds);
            $pushCount = count($pushSubscriptions);

            if ($pushCount > 0) {
                $notifications->sendPushNotifications($pushSubscriptions, [
                    'title' => sprintf('New updates from %s', $class->title),
                    'body' => 'Click to see what\'s new.',
                    'url' => $class->getUrl(),
                ]);
            }
        }

        return $this->asSuccess('Push notifications sent.');
    }
}
