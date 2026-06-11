<?php

namespace modules\notifications\services;

use craft\elements\User;
use modules\notifications\models\Login;
use modules\notifications\records\MagicLinkToken;
use yii\base\Component;

class Auth extends Component
{
    private const TOKEN_EXPIRY_MINUTES = 15;
    private const MAX_CODE_ATTEMPTS = 5;

    public static function newLogin($attrs): Login
    {
        $login = new Login();

        $login->setAttributes($attrs);

        return $login;
    }

    public function generateToken(string $email): MagicLinkToken
    {
        $token = bin2hex(random_bytes(32));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $expiresAt = new \DateTime();
        $expiresAt->modify('+'.self::TOKEN_EXPIRY_MINUTES.' minutes');

        $record = new MagicLinkToken();
        $record->email = $email;
        $record->token = $token;
        $record->code = $code;
        $record->expiresAt = $expiresAt->format('Y-m-d H:i:s');
        $record->save();

        return $record;
    }

    public function canRequestToken(string $email): bool
    {
        $since = (new \DateTime())->modify('-1 hour')->format('Y-m-d H:i:s');

        $count = MagicLinkToken::find()
            ->where(['email' => $email])
            ->andWhere(['>=', 'dateCreated', $since])
            ->count();

        return $count < 3;
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

    /**
     * Verifies a manually entered login code against the most recent
     * outstanding token for the given email. Returns the email on success,
     * or null if the code is wrong, expired, used, or out of attempts.
     */
    public function verifyCode(string $email, string $code): ?string
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $record = MagicLinkToken::find()
            ->where(['email' => $email, 'usedAt' => null])
            ->andWhere(['>', 'expiresAt', $now])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->one();

        if (!$record) {
            return null;
        }

        if ($record->attempts >= self::MAX_CODE_ATTEMPTS) {
            return null;
        }

        if (!hash_equals($record->code, $code)) {
            $record->attempts++;
            $record->save(false);

            return null;
        }

        $record->usedAt = $now;
        $record->save(false);

        return $record->email;
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

    public function sendLoginEmail(string $email, string $token, string $code, string $redirect = '/'): bool
    {
        $magicLink = \Craft::$app->getRequest()->getHostInfo()."/notifications/auth/verify?auth_token={$token}&redirect=".urlencode($redirect);
        $isNewUser = !\Craft::$app->getUsers()->getUserByUsernameOrEmail($email);
        $subject = $isNewUser ?
            'Welcome! Complete your registration for Titan Link' :
            'Your login code for Titan Link';
        $ctaText = $isNewUser ? 'Complete Registration' : 'Log In';
        $body = \Craft::$app->getView()->renderTemplate('_emails/magic-link', [
            'subject' => $subject,
            'isNewUser' => $isNewUser,
            'magicLink' => $magicLink,
            'code' => $code,
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
