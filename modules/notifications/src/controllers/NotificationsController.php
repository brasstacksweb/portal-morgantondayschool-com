<?php

namespace modules\registration\controllers;

use Craft;
use craft\web\Controller;
use modules\registration\services\NotificationService;
use yii\web\Response;

class NotificationsController extends Controller
{
    public function actionUnreadCount(): Response
    {
        $this->requireLogin();
        
        $userId = Craft::$app->getUser()->getId();
        $count = NotificationService::getUnreadCount($userId);
        
        return $this->asJson(['count' => $count]);
    }

    public function actionMarkRead(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        
        $request = Craft::$app->getRequest();
        $updateId = $request->getRequiredBodyParam('updateId');
        
        if (!is_numeric($updateId)) {
            return $this->asJson(['success' => false, 'message' => 'Invalid update ID']);
        }
        
        $userId = Craft::$app->getUser()->getId();
        $success = NotificationService::markAsRead($userId, (int)$updateId);
        
        return $this->asJson(['success' => $success]);
    }

    public function actionMarkAllRead(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        
        $userId = Craft::$app->getUser()->getId();
        $success = NotificationService::markAllAsRead($userId);
        
        return $this->asJson(['success' => $success]);
    }
}