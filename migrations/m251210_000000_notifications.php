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
            true
        );

        // Read status tracking (for in-app badges)
        $this->createTable('{{%user_update_read_status}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'updateEntryId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add foreign keys and indexes
        $this->addForeignKey(
            'fk_user_update_read_status_userId',
            '{{%user_update_read_status}}',
            'userId',
            '{{%users}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_user_update_read_status_updateEntryId',
            '{{%user_update_read_status}}',
            'updateEntryId',
            '{{%entries}}',
            'id',
            'CASCADE'
        );

        $this->createIndex(
            'idx_read_status_unique',
            '{{%user_update_read_status}}',
            ['userId', 'updateEntryId'],
            true
        );

        $this->createIndex(
            'idx_read_status_user',
            '{{%user_update_read_status}}',
            'userId'
        );

        $this->createIndex(
            'idx_read_status_update',
            '{{%user_update_read_status}}',
            'updateEntryId'
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

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%magic_link_tokens}}');
        $this->dropTableIfExists('{{%user_update_read_status}}');
        $this->dropTableIfExists('{{%user_class_subscriptions}}');

        return true;
    }
}
