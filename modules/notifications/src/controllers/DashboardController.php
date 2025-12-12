<?php

namespace modules\registration\controllers;

use craft\web\Controller;
use modules\registration\services\NotificationService;
use modules\registration\services\SubscriptionService;
use yii\web\Response;

class DashboardController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requireLogin();

        $unreadUpdates = NotificationService::getUnreadUpdates($user->id);
        $subscribedClasses = SubscriptionService::getSubscribedClasses($user->id);

        $subscribedClassIds = array_map(fn ($class) => $class->id, $subscribedClasses);

        $recentUpdates = [];
        if (!empty($subscribedClassIds)) {
            $recentUpdates = \Craft::$app->getEntries()
                ->section('classUpdates')
                ->relatedTo($subscribedClassIds)
                ->orderBy('postDate DESC')
                ->limit(20)
                ->all();
        }

        return $this->renderTemplate('dashboard', [
            'user' => $user,
            'unreadUpdates' => $unreadUpdates,
            'recentUpdates' => $recentUpdates,
            'subscribedClasses' => $subscribedClasses,
        ]);
    }
}
