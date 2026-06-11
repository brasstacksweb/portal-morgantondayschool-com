<?php

namespace modules\notifications\controllers;

use craft\web\Controller;
use modules\notifications\models\LoginCode;
use modules\notifications\NotificationsModule;
use yii\web\Response;

class AuthController extends Controller
{
    public const SESSION_EMAIL_KEY = 'notifications.loginEmail';
    public const SESSION_REDIRECT_KEY = 'notifications.loginRedirect';

    protected array|bool|int $allowAnonymous = ['send-code', 'verify', 'verify-code'];

    public function actionSendCode(): ?Response
    {
        $this->requirePostRequest();

        $request = \Craft::$app->getRequest();
        $auth = NotificationsModule::getInstance()->get('auth');
        $model = $auth::newLogin($request->getBodyParams());

        if (!$model->validate()) {
            return $this->asModelFailure($model, 'Invalid email address.');
        }

        if (!$auth->canRequestToken($model->email)) {
            return $this->asFailure('Too many requests. Please wait before requesting another code.');
        }

        try {
            $record = $auth->generateToken($model->email);
            $emailSent = $auth->sendLoginEmail($model->email, $record->token, $record->code, $model->redirect);

            if (!$emailSent) {
                return $this->asFailure('Failed to send email.');
            }

            // Hold the email in the session so the code-entry step never needs
            // to expose it in a URL or hidden field.
            $session = \Craft::$app->getSession();
            $session->set(self::SESSION_EMAIL_KEY, $model->email);
            $session->set(self::SESSION_REDIRECT_KEY, $this->sanitizeRedirect($model->redirect));

            return $this->asSuccess('Login code sent successfully.');
        } catch (\Exception $e) {
            \Craft::error("Error sending login code to {$model->email}: ".$e->getMessage(), __METHOD__);

            return $this->asFailure('An error occurred while processing your request.');
        }
    }

    public function actionVerifyCode(): ?Response
    {
        $this->requirePostRequest();

        $auth = NotificationsModule::getInstance()->get('auth');
        $session = \Craft::$app->getSession();
        $email = $session->get(self::SESSION_EMAIL_KEY);

        if (!$email) {
            return $this->asFailure('Your session expired. Please request a new code.');
        }

        $model = new LoginCode();
        $model->setAttributes(\Craft::$app->getRequest()->getBodyParams());

        if (!$model->validate()) {
            return $this->asModelFailure($model, 'Please enter the 6-digit code.');
        }

        if (!$auth->verifyCode($email, $model->code)) {
            return $this->asFailure('That code is invalid or expired. Please check the code or request a new one.');
        }

        $user = $auth->getOrCreateUser($email);

        if (!$user) {
            return $this->asFailure('Failed to create user account.');
        }

        if (!\Craft::$app->getUser()->login($user)) {
            return $this->asFailure('Failed to log in.');
        }

        $session->remove(self::SESSION_EMAIL_KEY);
        $session->remove(self::SESSION_REDIRECT_KEY);

        return $this->asSuccess('Logged in successfully.');
    }

    private function sanitizeRedirect(string $redirect): string
    {
        if ($redirect === '' || !str_starts_with($redirect, '/')) {
            return '/';
        }

        return $redirect;
    }

    public function actionVerify(): Response
    {
        $request = \Craft::$app->getRequest();
        $token = $request->getQueryParam('auth_token');

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

        $redirect = $request->getQueryParam('redirect', '/');

        // Validate redirect URL for security (prevent open redirects)
        $allowedPaths = ['/', '/subscriptions'];
        if (!in_array($redirect, $allowedPaths, true) && !str_starts_with($redirect, '/')) {
            $redirect = '/';
        }

        return $this->redirect($redirect);
    }
}
