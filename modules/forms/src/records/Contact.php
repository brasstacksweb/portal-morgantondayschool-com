<?php

namespace modules\forms\records;

use craft\db\ActiveRecord;

/**
 * @param {string} name
 * @param {string} email
 * @param {string} phone
 * @param {string} message
 * @param {string} pageUrl
 */
class Contact extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%forms_contact}}';
    }
}
