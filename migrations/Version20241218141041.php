<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241218141041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE history_account_lol DROP FOREIGN KEY FK_C63CEF7AB55FC8E8');
        $this->addSql('DROP INDEX IDX_C63CEF7AB55FC8E8 ON history_account_lol');
        $this->addSql('ALTER TABLE history_account_lol CHANGE riot_account_id_id riot_account_id INT NOT NULL');
        $this->addSql('ALTER TABLE history_account_lol ADD CONSTRAINT FK_C63CEF7AC6EAB37D FOREIGN KEY (riot_account_id) REFERENCES riot_account (id)');
        $this->addSql('CREATE INDEX IDX_C63CEF7AC6EAB37D ON history_account_lol (riot_account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE history_account_lol DROP FOREIGN KEY FK_C63CEF7AC6EAB37D');
        $this->addSql('DROP INDEX IDX_C63CEF7AC6EAB37D ON history_account_lol');
        $this->addSql('ALTER TABLE history_account_lol CHANGE riot_account_id riot_account_id_id INT NOT NULL');
        $this->addSql('ALTER TABLE history_account_lol ADD CONSTRAINT FK_C63CEF7AB55FC8E8 FOREIGN KEY (riot_account_id_id) REFERENCES riot_account (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_C63CEF7AB55FC8E8 ON history_account_lol (riot_account_id_id)');
    }
}
