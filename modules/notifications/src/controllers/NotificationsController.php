<?php

namespace modules\notifications\controllers;

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

        // TODO: Send push notification

        return $this->asSuccess('TODO: Actually send notification', ['entryId' => $entryId]);
    }

    public function actionUnreadCount(): Response
    {
        $this->requireLogin();

        $notifications = NotificationsModule::getInstance()->get('notifications');
        $userId = \Craft::$app->getUser()->getId();
        $count = $notifications->getUnreadCount($userId);

        return $this->asJson(['count' => $count]);
    }

    public function actionMarkRead(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = \Craft::$app->getRequest();
        $updateId = $request->getRequiredBodyParam('updateId');

        if (!is_numeric($updateId)) {
            return $this->asFailure('Invalid update ID');
        }

        $notifications = NotificationsModule::getInstance()->get('notifications');
        $userId = \Craft::$app->getUser()->getId();
        $success = $notifications->markAsRead($userId, (int) $updateId);

        return $this->asJson(['success' => $success]);
    }

    public function actionMarkAllRead(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $notifications = NotificationsModule::getInstance()->get('notifications');
        $userId = \Craft::$app->getUser()->getId();
        $success = $notifications->markAllAsRead($userId);

        return $this->asJson(['success' => $success]);
    }
}
