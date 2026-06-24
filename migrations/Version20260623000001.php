<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Brings databases created by Version20250424072609 into the corrected state:
 *  - the FULLTEXT index that the original migration failed to create on MariaDB
 *    (it used the MySQL-only `WITH PARSER ngram`);
 *  - path as VARCHAR instead of LONGTEXT;
 *  - telegram_id as BIGINT instead of INT (32-bit overflows on modern TG ids).
 * Idempotent — safe to run on a DB that already has the corrected schema.
 */
final class Version20260623000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'MariaDB-compatible FULLTEXT index + path VARCHAR + telegram_id BIGINT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audio ADD FULLTEXT INDEX IF NOT EXISTS idx_audio_title_artist (title, artist)');
        $this->addSql('ALTER TABLE audio MODIFY path VARCHAR(1024) NOT NULL');
        $this->addSql('ALTER TABLE user MODIFY telegram_id BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audio DROP INDEX idx_audio_title_artist');
        $this->addSql('ALTER TABLE audio MODIFY path LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE user MODIFY telegram_id INT DEFAULT NULL');
    }
}
