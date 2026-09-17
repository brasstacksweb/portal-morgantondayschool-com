<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * m260917_000000_titanfund migration.
 *
 * Online Titan Fund gifts. Offline gifts (checks, cash) are not here — they are
 * a Table field on the campaign entry, so staff can record them in the CP
 * without a custom admin screen (see docs/titan-fund.md §2).
 *
 * No donor name or email is stored. Those live in Stripe, which collects them at
 * checkout and sends the receipt; this table keeps only a hashed familyKey, the
 * one thing participation counting actually needs.
 */
class m260917_000000_titanfund extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%titanfund_donations}}', [
            'id' => $this->primaryKey(),
            'campaignEntryId' => $this->integer()->notNull(),
            // Nullable, and the FK is SET NULL: a gift outlives the account of
            // the parent who gave it.
            'userId' => $this->integer(),
            'familyKey' => $this->char(64)->notNull(),
            // Gross, in cents — what Stripe charged, including a covered fee.
            'amountCents' => $this->integer()->notNull(),
            'currency' => $this->char(3)->notNull()->defaultValue('usd'),
            'coveredFee' => $this->boolean()->notNull()->defaultValue(false),
            'stripePaymentIntentId' => $this->string()->notNull(),
            'stripeCheckoutSessionId' => $this->string(),
            'status' => $this->string(16)->notNull()->defaultValue('paid'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(
            'fk_titanfund_donations_campaignEntryId',
            '{{%titanfund_donations}}',
            'campaignEntryId',
            '{{%entries}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_titanfund_donations_userId',
            '{{%titanfund_donations}}',
            'userId',
            '{{%users}}',
            'id',
            'SET NULL'
        );

        // The whole concurrency story: the webhook and the return-from-checkout
        // confirmation both record the same gift, sometimes at once. Neither
        // coordinates with the other — a duplicate just fails this index and
        // record() reports "already recorded" (see docs/titan-fund.md §2).
        $this->createIndex(
            'idx_titanfund_donations_paymentIntent',
            '{{%titanfund_donations}}',
            'stripePaymentIntentId',
            true
        );

        // The totals query.
        $this->createIndex(
            'idx_titanfund_donations_campaign_status',
            '{{%titanfund_donations}}',
            ['campaignEntryId', 'status']
        );

        // The distinct-family count behind participation.
        $this->createIndex(
            'idx_titanfund_donations_familyKey',
            '{{%titanfund_donations}}',
            'familyKey'
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%titanfund_donations}}');

        return true;
    }
}
