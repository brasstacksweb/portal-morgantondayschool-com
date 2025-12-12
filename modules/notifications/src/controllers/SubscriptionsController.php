<?php

namespace modules\notifications\controllers;

use craft\web\Controller;
use modules\notifications\NotificationsModule;
use yii\web\Response;

class SubscriptionsController extends Controller
{
    public function actionSave(): Response
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
            return $this->asFailure('Failed to save class selections');
        }

        $user->setFieldValue('hasOnboarded', true);
        $success = \Craft::$app->getElements()->saveElement($user);

        if (!$success) {
            \Craft::error('Failed to mark onboarding complete for user '.$user->id, __METHOD__);

            return $this->asFailure('Failed to complete onboarding');
        }

        return $this->asSuccess();
    }
}
