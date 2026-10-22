<?php

namespace modules\titanfund\models;

use modules\components\models\Form as BaseForm;
use modules\titanfund\services\Donations;

/**
 * Titan Fund donation form model.
 *
 * Rendered by the shared _components/form.twig like every other form. What the
 * donor chooses is an amount and whether to cover the fee — the campaign is
 * resolved server-side from the live entry rather than trusted from the form,
 * and the donor's name and email are collected by Stripe Checkout, not by us.
 */
class Donation extends BaseForm
{
    /** The `amount` option that reveals the free-form amount field. */
    public const OTHER = 'other';

    /** A preset amount in cents, or OTHER. */
    public string $amount = '';

    /** Whole dollars; only used when `amount` is OTHER. */
    public string $customAmount = '';

    /** A single-option checkbox group, so it posts as an array. */
    public array $coverFee = [];

    /** Hashed hidden input: where Stripe sends the donor back to. */
    public string $returnPath = '';

    public string $submitText = 'Continue to Payment';

    /**
     * Preset gift amounts in whole dollars, from the campaign entry. Set by
     * Donations::newDonation(), never from submitted data.
     *
     * @var int[]
     */
    public array $presets = [];

    public function scenarios(): array
    {
        return [
            self::SCENARIO_DEFAULT => [
                'token',
                'amount',
                'customAmount',
                'coverFee',
                'returnPath',
            ],
        ];
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['amount'], 'required', 'message' => 'Please choose an amount.'],
            [['customAmount'], 'trim'],
            [
                ['customAmount'],
                'required',
                'when' => fn (self $model) => $model->amount === self::OTHER,
                'message' => 'Please enter an amount.',
            ],
            [['customAmount'], 'number', 'message' => 'Please enter an amount in dollars.'],
            [['coverFee'], 'each', 'rule' => ['in', 'range' => ['1']]],
            [['returnPath'], 'validateHash'],
            [['amount'], 'validateAmount'],
        ]);
    }

    /**
     * Bounds are technical, not policy: the floor is Stripe's own minimum charge
     * and the ceiling catches a mistyped card number in the amount box. Errors
     * land on whichever field the donor actually used.
     */
    public function validateAmount(): void
    {
        $isOther = $this->amount === self::OTHER;
        $attribute = $isOther ? 'customAmount' : 'amount';

        if ($this->hasErrors($attribute)) {
            return;
        }

        if (!$isOther && !ctype_digit($this->amount)) {
            $this->addError('amount', 'Please choose an amount.');

            return;
        }

        $cents = $this->getAmountCents();

        if ($cents < Donations::MIN_CENTS) {
            $this->addError($attribute, 'The smallest gift we can process online is $1.');
        } elseif ($cents > Donations::MAX_CENTS) {
            $this->addError($attribute, 'Please contact the school office to make a gift this size.');
        }
    }

    public function getAmountCents(): int
    {
        if ($this->amount === self::OTHER) {
            return (int) round((float) $this->customAmount * 100);
        }

        return (int) $this->amount;
    }

    public function coversFee(): bool
    {
        return in_array('1', $this->coverFee, true);
    }

    public function attributeTypes(): array
    {
        return array_merge(parent::attributeTypes(), [
            'amount' => 'radio-pills',
            'customAmount' => 'number',
            'coverFee' => 'checkbox',
            'returnPath' => 'hidden',
        ]);
    }

    public function attributeLabels(): array
    {
        return [
            'amount' => 'Gift Amount',
            'customAmount' => 'Other Amount',
            'coverFee' => 'Processing Fee',
        ];
    }

    public function attributePlaceholders(): array
    {
        return [
            'customAmount' => 'Amount in dollars',
        ];
    }

    public function attributeOptions(): array
    {
        $formatter = \Craft::$app->getFormatter();

        return [
            'amount' => array_merge(
                array_map(fn (int $dollars) => [
                    'label' => $formatter->asCurrency($dollars, 'USD', [], [], true),
                    'value' => (string) ($dollars * 100),
                ], $this->presets),
                [['label' => 'Other', 'value' => self::OTHER]],
            ),
            'coverFee' => [
                ['label' => 'Add the processing fee so the school receives my full gift', 'value' => '1'],
            ],
        ];
    }

    public function attributeConditionals(): array
    {
        return [
            'customAmount' => ['name' => 'amount', 'value' => self::OTHER],
        ];
    }

    public function getActionPath(): string
    {
        return 'titan-fund/donations/checkout';
    }
}
