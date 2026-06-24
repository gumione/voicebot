<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Multi-tenancy: bot tenants + per-bot ownership of samples.
 */
final class Version20260623120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bot tenants and scope audio to a bot (+ warming status)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE bot (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            username VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            storage_chat_id BIGINT DEFAULT NULL,
            webhook_token VARCHAR(64) NOT NULL,
            is_active TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            UNIQUE INDEX uniq_bot_webhook_token (webhook_token),
            UNIQUE INDEX uniq_bot_username (username),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE audio ADD bot_id INT DEFAULT NULL, ADD status VARCHAR(16) DEFAULT 'pending' NOT NULL");
        $this->addSql('ALTER TABLE audio ADD CONSTRAINT FK_audio_bot FOREIGN KEY (bot_id) REFERENCES bot (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_187D369592C1C487 ON audio (bot_id)');

        // Already-warmed rows are ready, not pending.
        $this->addSql("UPDATE audio SET status = 'ready' WHERE file_id IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audio DROP FOREIGN KEY FK_audio_bot');
        $this->addSql('DROP INDEX IDX_187D369592C1C487 ON audio');
        $this->addSql('ALTER TABLE audio DROP bot_id, DROP status');
        $this->addSql('DROP TABLE bot');
    }
}
