<?php

namespace modules\notifications\controllers;

use craft\elements\User;
use craft\web\Controller;
use modules\notifications\models\Login;
use modules\notifications\services\MagicLinks;
use yii\web\Response;

class AuthController extends Controller
{
    protected array|bool|int $allowAnonymous = ['send-magic-link', 'verify'];

    public function actionSendMagicLink(): Response
    {
        $this->requirePostRequest();

        $request = \Craft::$app->getRequest();
        $model = new Login();

        $model->setAttributes($request->getBodyParams());

        if (!$model->validate()) {
            return $this->asModelFailure($model, 'Invalid email address');
        }

        try {
            $token = MagicLinks::generateToken($model->email);
            $emailSent = MagicLinks::sendMagicLinkEmail($model->email, $token);

            if (!$emailSent) {
                return $this->asFailure('Failed to send email');
            }

            return $this->asSuccess();
        } catch (\Exception $e) {
            \Craft::error("Error sending magic link to {$model->email}: ".$e->getMessage(), __METHOD__);

            return $this->asFailure('An error occurred while processing your request');
        }
    }

    public function actionVerify(): Response
    {
        $request = \Craft::$app->getRequest();
        $token = $request->getQueryParam('token');

        if (!$token) {
            \Craft::$app->getSession()->setError('Invalid or missing token');

            return $this->redirect('/login');
        }

        $email = MagicLinks::validateToken($token);

        if (!$email) {
            \Craft::$app->getSession()->setError('Invalid or expired token');

            return $this->redirect('/login');
        }

        MagicLinks::markTokenUsed($token);

        $user = $this->getOrCreateUser($email);
        if (!$user) {
            \Craft::$app->getSession()->setError('Failed to create user account');

            return $this->redirect('/login');
        }

        if (!\Craft::$app->getUser()->login($user)) {
            \Craft::$app->getSession()->setError('Failed to log in');

            return $this->redirect('/login');
        }

        $hasOnboarded = $user->getFieldValue('hasOnboarded') ?? false;

        if (!$hasOnboarded) {
            return $this->redirect('/onboarding');
        }

        return $this->redirect('/dashboard');
    }

    private function getOrCreateUser(string $email): ?User
    {
        $users = \Craft::$app->getUsers();

        if ($user = $users->getUserByUsernameOrEmail($email)) {
            return $user;
        }

        $user = new User();
        $user->email = $email;
        $user->username = $email;

        $segments = explode('@', $email);
        $user->firstName = ucfirst($segments[0]);

        if (!\Craft::$app->getElements()->saveElement($user)) {
            \Craft::error('Failed to create user: '.implode(', ', $user->getErrorSummary(true)), __METHOD__);

            return null;
        }

        $users->activateUser($user);

        $parentsGroup = \Craft::$app->getUserGroups()->getGroupByHandle('parents');
        if ($parentsGroup) {
            $users->assignUserToGroups($user->id, [$parentsGroup->id]);
        }

        return $user;
    }
}
