<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230510142620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audio ADD file_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE FULLTEXT INDEX IDX_187D36952B36786B ON audio (title)');
        $this->addSql('ALTER TABLE user ADD telegram_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_187D36952B36786B ON audio');
        $this->addSql('ALTER TABLE audio DROP file_id');
        $this->addSql('ALTER TABLE user DROP telegram_id');
    }
}
