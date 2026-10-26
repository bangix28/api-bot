<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261025205324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LAN : événements et participants (comptes Riot)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lan_event (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lan_event_participant (lan_event_id INT NOT NULL, riot_account_id INT NOT NULL, INDEX IDX_771C26A2B1C72398 (lan_event_id), INDEX IDX_771C26A2C6EAB37D (riot_account_id), PRIMARY KEY (lan_event_id, riot_account_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lan_event_participant ADD CONSTRAINT FK_771C26A2B1C72398 FOREIGN KEY (lan_event_id) REFERENCES lan_event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lan_event_participant ADD CONSTRAINT FK_771C26A2C6EAB37D FOREIGN KEY (riot_account_id) REFERENCES riot_account (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lan_event_participant DROP FOREIGN KEY FK_771C26A2B1C72398');
        $this->addSql('ALTER TABLE lan_event_participant DROP FOREIGN KEY FK_771C26A2C6EAB37D');
        $this->addSql('DROP TABLE lan_event');
        $this->addSql('DROP TABLE lan_event_participant');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
