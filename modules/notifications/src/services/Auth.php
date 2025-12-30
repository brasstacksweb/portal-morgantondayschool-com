<?php

namespace modules\notifications\services;

use craft\elements\User;
use modules\notifications\models\Login;
use modules\notifications\records\MagicLinkToken;
use yii\base\Component;

class Auth extends Component
{
    private const TOKEN_EXPIRY_MINUTES = 15;

    public static function newLogin($attrs): Login
    {
        $login = new Login();

        $login->setAttributes($attrs);

        return $login;
    }

    public function generateToken(string $email): string
    {
        $token = bin2hex(random_bytes(32));

        $expiresAt = new \DateTime();
        $expiresAt->modify('+'.self::TOKEN_EXPIRY_MINUTES.' minutes');

        $record = new MagicLinkToken();
        $record->email = $email;
        $record->token = $token;
        $record->expiresAt = $expiresAt->format('Y-m-d H:i:s');
        $record->save();

        return $token;
    }

    public function validateToken(string $token): ?string
    {
        $record = MagicLinkToken::find()
            ->where(['token' => $token])
            ->one();

        if (!$record) {
            return null;
        }

        $now = new \DateTime();
        $expiresAt = new \DateTime($record->expiresAt);

        if ($now > $expiresAt) {
            return null;
        }

        if ($record->usedAt !== null) {
            return null;
        }

        return $record->email;
    }

    public function markTokenUsed(string $token): void
    {
        $record = MagicLinkToken::find()
            ->where(['token' => $token])
            ->one();

        if ($record) {
            $record->usedAt = (new \DateTime())->format('Y-m-d H:i:s');
            $record->save();
        }
    }

    public function cleanupExpiredTokens(): void
    {
        $now = new \DateTime();

        MagicLinkToken::deleteAll([
            'and',
            ['<', 'expiresAt', $now->format('Y-m-d H:i:s')],
        ]);

        $yesterday = new \DateTime('-24 hours');

        MagicLinkToken::deleteAll([
            'and',
            ['not', ['usedAt' => null]],
            ['<', 'usedAt', $yesterday->format('Y-m-d H:i:s')],
        ]);
    }

    public function sendMagicLinkEmail(string $email, string $token, string $redirect = '/'): bool
    {
        $magicLink = \Craft::$app->getRequest()->getHostInfo()."/notifications/auth/verify?token={$token}&redirect=".urlencode($redirect);
        $isNewUser = !\Craft::$app->getUsers()->getUserByUsernameOrEmail($email);
        $subject = $isNewUser ?
            'Welcome! Complete your registration for Titan Link' :
            'Your login link for Titan Link';
        $ctaText = $isNewUser ? 'Complete Registration' : 'Log In';
        $body = \Craft::$app->getView()->renderTemplate('_emails/magic-link', [
            'subject' => $subject,
            'isNewUser' => $isNewUser,
            'magicLink' => $magicLink,
            'ctaText' => $ctaText,
        ]);

        return \Craft::$app->getMailer()
            ->compose()
            ->setTo($email)
            ->setSubject($subject)
            ->setHtmlBody($body)
            ->send();
    }

    public function getOrCreateUser(string $email): ?User
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
