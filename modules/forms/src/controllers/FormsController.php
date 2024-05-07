<?php

namespace modules\forms\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\web\Controller;
use craft\web\Response;
use modules\forms\services\Forms;
use yii\base\Model;
use yii\web\ServerErrorHttpException;

class FormsController extends Controller
{
    protected array|int|bool $allowAnonymous = ['submit'];

    public function actionSubmit(): ?Response
    {
        $this->requirePostRequest();
        $params = $this->request->getBodyParams();
        $handle = Craft::$app->getSecurity()->validateData($params['handle']);
        $scenario = Craft::$app->getSecurity()->validateData($params['scenario']);

        $form = Forms::newForm($handle, ArrayHelper::without($params, 'scenario'), $scenario);

        if (!$form->validate()) {
            return $this->asFailure(
                'Failed Validation',
                ['errors' => $form->getErrors()],
            );
        }

        if (!$form->saveLocal()) {
            $this->logFormError('Failed to save local data for form', $form);

            throw new ServerErrorHttpException('We cannot receive your request right now. Please try again soon.');
        }

        if (!App::devMode() && !$form->saveRemote()) {
            $this->logFormError('Failed to save remote data for form', $form);
        }

        if (!App::devMode() && !$form->sendNotificationEmail()) {
            $this->logFormError('Failed to send notification email for form', $form);
        }

        if (!App::devMode() && !$form->sendConfirmationEmail()) {
            $this->logFormError('Failed to send confirmation email for form', $form);
        }

        return $this->asSuccess('Form Submitted');
    }

    private function logFormError(string $message, Model $form): void
    {
        Craft::error(
            sprintf('%s: %s', $message, json_encode($form->getAttributes(), true)),
            'forms-module'
        );
    }
}
