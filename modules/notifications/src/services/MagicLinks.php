<?php

namespace modules\notifications\services;

use modules\notifications\records\MagicLinkToken;

class MagicLinks
{
    private const TOKEN_EXPIRY_MINUTES = 15;

    public static function generateToken(string $email): string
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

    public static function validateToken(string $token): ?string
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

    public static function markTokenUsed(string $token): void
    {
        $record = MagicLinkToken::find()
            ->where(['token' => $token])
            ->one();

        if ($record) {
            $record->usedAt = (new \DateTime())->format('Y-m-d H:i:s');
            $record->save();
        }
    }

    public static function cleanupExpiredTokens(): void
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

    public static function sendMagicLinkEmail(string $email, string $token): bool
    {
        $magicLink = \Craft::$app->getRequest()->getHostInfo()."/notifications/auth/verify?token={$token}";
        $isNewUser = !\Craft::$app->getUsers()->getUserByUsernameOrEmail($email);
        $subject = $isNewUser ?
            'Welcome! Complete your registration for Titan Link' :
            'Your login link for Titan Link';
        $ctaText = $isNewUser ? 'Complete Registration' : 'Log In';
        $body = \Craft::$app->getView()->renderTemplate('emails/magic-link', [
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
}
