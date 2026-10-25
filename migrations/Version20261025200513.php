<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261025200513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LAN (événements et participants) et statistiques ARAM : Chaos de l\'historique des matchs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lan_event (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lan_event_participant (lan_event_id INT NOT NULL, riot_account_id INT NOT NULL, INDEX IDX_771C26A2B1C72398 (lan_event_id), INDEX IDX_771C26A2C6EAB37D (riot_account_id), PRIMARY KEY (lan_event_id, riot_account_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lan_event_participant ADD CONSTRAINT FK_771C26A2B1C72398 FOREIGN KEY (lan_event_id) REFERENCES lan_event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lan_event_participant ADD CONSTRAINT FK_771C26A2C6EAB37D FOREIGN KEY (riot_account_id) REFERENCES riot_account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE history_account_lol ADD heals_on_teammates INT DEFAULT NULL, ADD damage_shielded_on_teammates INT DEFAULT NULL, ADD time_ccing_others INT DEFAULT NULL, ADD team_damage_percentage DOUBLE PRECISION DEFAULT NULL, ADD damage_taken_on_team_percentage DOUBLE PRECISION DEFAULT NULL, ADD snowballs_hit INT DEFAULT NULL, ADD skillshots_dodged INT DEFAULT NULL, ADD solo_kills INT DEFAULT NULL, ADD outnumbered_kills INT DEFAULT NULL, ADD augments JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lan_event_participant DROP FOREIGN KEY FK_771C26A2B1C72398');
        $this->addSql('ALTER TABLE lan_event_participant DROP FOREIGN KEY FK_771C26A2C6EAB37D');
        $this->addSql('DROP TABLE lan_event');
        $this->addSql('DROP TABLE lan_event_participant');
        $this->addSql('ALTER TABLE history_account_lol DROP heals_on_teammates, DROP damage_shielded_on_teammates, DROP time_ccing_others, DROP team_damage_percentage, DROP damage_taken_on_team_percentage, DROP snowballs_hit, DROP skillshots_dodged, DROP solo_kills, DROP outnumbered_kills, DROP augments');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
