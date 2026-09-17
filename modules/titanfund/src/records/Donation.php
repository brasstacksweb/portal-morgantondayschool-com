<?php

namespace modules\titanfund\records;

use craft\db\ActiveRecord;
use modules\titanfund\services\Donations;

/**
 * Titan Fund online donation record.
 *
 * @property int     $id
 * @property int     $campaignEntryId
 * @property ?int    $userId
 * @property string  $familyKey
 * @property int     $amountCents
 * @property string  $currency
 * @property bool    $coveredFee
 * @property string  $stripePaymentIntentId
 * @property ?string $stripeCheckoutSessionId
 * @property string  $status
 * @property string  $dateCreated
 * @property string  $dateUpdated
 * @property string  $uid
 */
class Donation extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%titanfund_donations}}';
    }

    public function rules(): array
    {
        return [
            [
                [
                    'campaignEntryId',
                    'familyKey',
                    'amountCents',
                    'stripePaymentIntentId',
                ],
                'required',
            ],
            [['campaignEntryId', 'userId', 'amountCents'], 'integer'],
            // Stripe rejects charges under $0.50; anything at or below zero is a
            // bug on our side rather than a donor decision.
            [['amountCents'], 'integer', 'min' => 50],
            [['coveredFee'], 'boolean'],
            [['status'], 'in', 'range' => [Donations::STATUS_PAID, Donations::STATUS_REFUNDED]],
            [
                ['stripePaymentIntentId'],
                'unique',
                'message' => 'This payment has already been recorded.',
            ],
        ];
    }
}
