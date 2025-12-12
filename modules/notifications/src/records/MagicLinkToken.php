<?php

namespace modules\notifications\records;

use craft\db\ActiveRecord;

/**
 * Magic Link Token Record.
 *
 * @property int         $id
 * @property string      $email
 * @property string      $token
 * @property string      $expiresAt
 * @property null|string $usedAt
 * @property string      $dateCreated
 * @property string      $uid
 */
class MagicLinkToken extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%magic_link_tokens}}';
    }

    public function rules(): array
    {
        return [
            [['email', 'token', 'expiresAt'], 'required'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['token', 'string', 'length' => 64],
            ['token', 'unique'],
            [['expiresAt', 'usedAt'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
        ];
    }
}
