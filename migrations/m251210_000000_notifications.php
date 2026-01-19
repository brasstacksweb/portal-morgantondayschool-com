<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * m251210_000000_registration_module migration.
 */
class m251210_000000_notifications extends Migration
{
    public function safeUp(): bool
    {
        // User class subscriptions (which classes each parent follows)
        $this->createTable('{{%user_class_subscriptions}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'classEntryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add foreign keys and indexes
        $this->addForeignKey(
            'fk_user_class_subscriptions_userId',
            '{{%user_class_subscriptions}}',
            'userId',
            '{{%users}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_user_class_subscriptions_classEntryId',
            '{{%user_class_subscriptions}}',
            'classEntryId',
            '{{%entries}}',
            'id',
            'CASCADE'
        );

        $this->createIndex(
            'idx_user_class_sub_unique',
            '{{%user_class_subscriptions}}',
            ['userId', 'classEntryId'],
            true
        );

        $this->createIndex(
            'idx_user_class_sub_user',
            '{{%user_class_subscriptions}}',
            'userId'
        );

        $this->createIndex(
            'idx_user_class_sub_class',
            '{{%user_class_subscriptions}}',
            'classEntryId'
        );

        // User push subscriptions (web push notifications)
        $this->createTable('{{%user_push_subscriptions}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'endpoint' => $this->string(255)->notNull()->unique(),
            'p256dhKey' => $this->text()->notNull(),
            'authKey' => $this->text()->notNull(),
            'lastUsed' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add foreign keys and indexes
        $this->addForeignKey(
            'fk_user_push_subscriptions_userId',
            '{{%user_push_subscriptions}}',
            'userId',
            '{{%users}}',
            'id',
            'CASCADE'
        );

        $this->createIndex(
            'idx_user_push_subscriptions_userId',
            '{{%user_push_subscriptions}}',
            'userId',
        );

        // Magic link tokens for passwordless authentication
        $this->createTable('{{%magic_link_tokens}}', [
            'id' => $this->primaryKey(),
            'email' => $this->string(255)->notNull(),
            'token' => $this->char(64)->notNull(),
            'expiresAt' => $this->dateTime()->notNull(),
            'usedAt' => $this->dateTime()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add indexes
        $this->createIndex(
            'idx_magic_link_token',
            '{{%magic_link_tokens}}',
            'token',
            true
        );

        $this->createIndex(
            'idx_magic_link_email',
            '{{%magic_link_tokens}}',
            'email'
        );

        $this->createIndex(
            'idx_magic_link_expires',
            '{{%magic_link_tokens}}',
            'expiresAt'
        );

        // Notification logs for rate limiting (tracks when teachers send notifications)
        $this->createTable('{{%notification_logs}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(), // Teacher who sent the notification
            'classEntryId' => $this->integer()->notNull(), // Class the notification was for
            'recipientCount' => $this->integer()->notNull()->defaultValue(0), // Number of recipients
            'dateCreated' => $this->dateTime()->notNull(), // When the notification was sent
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add foreign keys and indexes
        $this->addForeignKey(
            'fk_notification_logs_userId',
            '{{%notification_logs}}',
            'userId',
            '{{%users}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_notification_logs_classEntryId',
            '{{%notification_logs}}',
            'classEntryId',
            '{{%entries}}',
            'id',
            'CASCADE'
        );

        $this->createIndex(
            'idx_notification_logs_user_class',
            '{{%notification_logs}}',
            ['userId', 'classEntryId']
        );

        $this->createIndex(
            'idx_notification_logs_date_created',
            '{{%notification_logs}}',
            'dateCreated'
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%notification_logs}}');
        $this->dropTableIfExists('{{%magic_link_tokens}}');
        $this->dropTableIfExists('{{%user_class_subscriptions}}');
        $this->dropTableIfExists('{{%user_push_subscriptions}}');

        return true;
    }
}
