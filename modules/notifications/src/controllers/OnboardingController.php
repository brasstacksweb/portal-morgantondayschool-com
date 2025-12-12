<?php

namespace modules\notifications\controllers;

use craft\web\Controller;
use modules\notifications\models\Subscriptions as SubscriptionsModel;
use modules\notifications\services\Subscriptions;
use yii\web\Response;

class OnboardingController extends Controller
{
    public function actionSaveSubscriptions(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = \Craft::$app->getRequest();
        $model = new SubscriptionsModel();

        $model->setAttributes($request->getBodyParams());

        if (!$model->validate()) {
            return $this->asModelFailure($model);
        }

        $user = \Craft::$app->getUser()->getIdentity();
        $subscriptionsSaved = Subscriptions::saveSubscriptions($user->id, $model->classes);

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
