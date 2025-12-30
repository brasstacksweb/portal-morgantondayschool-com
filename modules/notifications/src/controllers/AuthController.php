<?php

namespace modules\notifications\controllers;

use craft\web\Controller;
use modules\notifications\NotificationsModule;
use yii\web\Response;

class AuthController extends Controller
{
    protected array|bool|int $allowAnonymous = ['send-magic-link', 'verify'];

    public function actionSendMagicLink(): ?Response
    {
        $this->requirePostRequest();

        $request = \Craft::$app->getRequest();
        $auth = NotificationsModule::getInstance()->get('auth');
        $model = $auth::newLogin($request->getBodyParams());

        if (!$model->validate()) {
            return $this->asModelFailure($model, 'Invalid email address.');
        }

        try {
            $token = $auth->generateToken($model->email);
            $emailSent = $auth->sendMagicLinkEmail($model->email, $token, $model->redirect);

            if (!$emailSent) {
                return $this->asFailure('Failed to send email.');
            }

            return $this->asSuccess('Magic link sent successfully.');
        } catch (\Exception $e) {
            \Craft::error("Error sending magic link to {$model->email}: ".$e->getMessage(), __METHOD__);

            return $this->asFailure('An error occurred while processing your request.');
        }
    }

    public function actionVerify(): Response
    {
        $request = \Craft::$app->getRequest();
        $token = $request->getQueryParam('token');

        if (!$token) {
            \Craft::$app->getSession()->setError('Invalid or missing token.');

            return $this->redirect('/login');
        }

        $auth = NotificationsModule::getInstance()->get('auth');
        $email = $auth->validateToken($token);

        if (!$email) {
            \Craft::$app->getSession()->setError('Invalid or expired token.');

            return $this->redirect('/login');
        }

        $auth->markTokenUsed($token);

        $user = $auth->getOrCreateUser($email);

        if (!$user) {
            \Craft::$app->getSession()->setError('Failed to create user account.');

            return $this->redirect('/login');
        }

        if (!\Craft::$app->getUser()->login($user)) {
            \Craft::$app->getSession()->setError('Failed to log in.');

            return $this->redirect('/login');
        }

        $hasOnboarded = $user->getFieldValue('hasOnboarded');
        $redirect = $request->getQueryParam('redirect', '/');

        // Validate redirect URL for security (prevent open redirects)
        $allowedPaths = ['/', '/subscriptions'];
        if (!in_array($redirect, $allowedPaths, true) && !str_starts_with($redirect, '/')) {
            $redirect = '/';
        }

        if (!$hasOnboarded) {
            return $this->redirect('/subscriptions'); // Always go to subscriptions for onboarding
        }

        return $this->redirect($redirect);
    }
}
