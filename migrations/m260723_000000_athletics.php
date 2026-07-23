<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * m260723_000000_athletics migration.
 */
class m260723_000000_athletics extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%athletics_signups}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'teamEntryId' => $this->integer()->notNull(),
            'participantKey' => $this->char(64)->notNull(),
            'participantFirstName' => $this->string()->notNull(),
            'participantLastName' => $this->string()->notNull(),
            'dateOfBirth' => $this->date()->notNull(),
            'guardianEmail' => $this->string()->notNull(),
            'guardianPhone' => $this->string()->notNull(),
            'interestedInCoaching' => $this->boolean()->notNull()->defaultValue(false),
            'status' => $this->string(16)->notNull()->defaultValue('interested'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(
            'fk_athletics_signups_userId',
            '{{%athletics_signups}}',
            'userId',
            '{{%users}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_athletics_signups_teamEntryId',
            '{{%athletics_signups}}',
            'teamEntryId',
            '{{%entries}}',
            'id',
            'CASCADE'
        );

        // Duplicate prevention: one participant per team. This is also the only
        // concurrency guard the register path needs (see spec §2).
        $this->createIndex(
            'idx_athletics_signups_team_participant',
            '{{%athletics_signups}}',
            ['teamEntryId', 'participantKey'],
            true
        );

        // Roster and count queries filter by team + status.
        $this->createIndex(
            'idx_athletics_signups_team_status',
            '{{%athletics_signups}}',
            ['teamEntryId', 'status']
        );

        $this->createIndex(
            'idx_athletics_signups_user',
            '{{%athletics_signups}}',
            'userId'
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%athletics_signups}}');

        return true;
    }
}
