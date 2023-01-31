<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230129125839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD riot_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649C6EAB37D FOREIGN KEY (riot_account_id) REFERENCES riot_account (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649C6EAB37D ON user (riot_account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649C6EAB37D');
        $this->addSql('DROP INDEX UNIQ_8D93D649C6EAB37D ON user');
        $this->addSql('ALTER TABLE user DROP riot_account_id');
    }
}
