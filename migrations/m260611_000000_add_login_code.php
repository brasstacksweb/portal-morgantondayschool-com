<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * m260611_000000_add_login_code migration.
 *
 * Adds a one-time numeric code (and verification attempt counter) to the
 * magic link tokens table so users can log in by typing a code instead of
 * following a link. This is needed because iOS PWAs open emailed links in
 * Safari rather than the installed app, breaking the magic-link flow.
 */
class m260611_000000_add_login_code extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%magic_link_tokens}}';

        if (!$this->db->columnExists($table, 'code')) {
            $this->addColumn($table, 'code', $this->char(6)->after('token'));
        }

        if (!$this->db->columnExists($table, 'attempts')) {
            $this->addColumn($table, 'attempts', $this->integer()->notNull()->defaultValue(0)->after('code'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $table = '{{%magic_link_tokens}}';

        if ($this->db->columnExists($table, 'attempts')) {
            $this->dropColumn($table, 'attempts');
        }

        if ($this->db->columnExists($table, 'code')) {
            $this->dropColumn($table, 'code');
        }

        return true;
    }
}
