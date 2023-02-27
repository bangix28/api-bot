<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230204153447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account DROP FOREIGN KEY FK_79C2D42DA76ED395');
        $this->addSql('ALTER TABLE riot_account CHANGE user_id user_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE riot_account ADD CONSTRAINT FK_79C2D42DA76ED395 FOREIGN KEY (user_id) REFERENCES user (discordId)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account DROP FOREIGN KEY FK_79C2D42DA76ED395');
        $this->addSql('ALTER TABLE riot_account CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE riot_account ADD CONSTRAINT FK_79C2D42DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
