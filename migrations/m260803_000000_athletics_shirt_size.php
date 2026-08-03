<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * m260803_000000_athletics_shirt_size migration.
 *
 * Adds an optional shirt size to athletics signups. v1 deliberately left this
 * to a coach follow-up so the form stayed short; collecting it up front saves
 * that round trip. The column is nullable and the field is optional, so a
 * parent who is only "interested" can still register without answering — null
 * means "not collected", which is distinct from any real size.
 */
class m260803_000000_athletics_shirt_size extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%athletics_signups}}';

        if (!$this->db->columnExists($table, 'shirtSize')) {
            $this->addColumn($table, 'shirtSize', $this->string(8)->after('dateOfBirth'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $table = '{{%athletics_signups}}';

        if ($this->db->columnExists($table, 'shirtSize')) {
            $this->dropColumn($table, 'shirtSize');
        }

        return true;
    }
}
