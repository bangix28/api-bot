<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240927130959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account CHANGE riot_id riot_id VARCHAR(255) NOT NULL, CHANGE puuid puuid VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_79C2D42DAF5F8B9B ON riot_account (riot_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_79C2D42DAF5F8B9B ON riot_account');
        $this->addSql('ALTER TABLE riot_account CHANGE riot_id riot_id VARCHAR(255) DEFAULT NULL, CHANGE puuid puuid VARCHAR(255) DEFAULT NULL');
    }
}
