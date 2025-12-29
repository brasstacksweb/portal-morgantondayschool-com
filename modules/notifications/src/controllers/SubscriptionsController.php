<?php

namespace modules\notifications\controllers;

use craft\web\Controller;
use modules\notifications\NotificationsModule;
use yii\web\Response;

class SubscriptionsController extends Controller
{
    public function actionSubscribePush(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = \Craft::$app->getRequest();
        $subscriptions = NotificationsModule::getInstance()->get('subscriptions');
        $userId = \Craft::$app->getUser()->getIdentity()->id;
        $subscription = $request->getBodyParam('subscription');
        $endpoint = $request->getBodyParam('endpoint');
        $p256dhKey = $request->getBodyParam('p256dhKey');
        $authKey = $request->getBodyParam('authKey');

        if (!$subscriptions->savePushSubscription($userId, $endpoint, $p256dhKey, $authKey)) {
            return $this->asFailure('Failed to save push subscription.');
        }

        return $this->asSuccess('Push subscription saved successfully.');
    }

    public function actionUnsubscribePush(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = \Craft::$app->getRequest();
        $subscriptions = NotificationsModule::getInstance()->get('subscriptions');
        $userId = \Craft::$app->getUser()->getIdentity()->id;
        $endpoint = $request->getBodyParam('endpoint');

        if (!$subscriptions->removePushSubscription($userId, $endpoint)) {
            return $this->asFailure('Failed to remove push subscription.');
        }

        return $this->asSuccess('Push subscription removed successfully.');
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = \Craft::$app->getRequest();
        $subscriptions = NotificationsModule::getInstance()->get('subscriptions');
        $model = $subscriptions::newSubscriptions($request->getBodyParams());

        if (!$model->validate()) {
            return $this->asModelFailure($model);
        }

        $user = \Craft::$app->getUser()->getIdentity();
        $subscriptionsSaved = $subscriptions->saveSubscriptions($user->id, $model->classes);

        if (!$subscriptionsSaved) {
            return $this->asFailure('Failed to save class selections.');
        }

        $user->setFieldValue('hasOnboarded', true);
        $success = \Craft::$app->getElements()->saveElement($user);

        if (!$success) {
            return $this->asFailure('Failed to save class subscriptions.');
        }

        \Craft::$app->getSession()->setSuccess('Your class subscriptions are saved.');

        return $this->asSuccess('Class selections saved successfully.');
    }
}
