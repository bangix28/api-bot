<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240924192206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, discord_id VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE riot_account ADD puuid VARCHAR(255) DEFAULT NULL, ADD score INT DEFAULT NULL, DROP riot_puuid, DROP summoner_ranked_solo_wins, CHANGE user_id user_id INT DEFAULT NULL, CHANGE riot_id riot_id VARCHAR(255) DEFAULT NULL, CHANGE summoner_name summoner_name VARCHAR(255) DEFAULT NULL, CHANGE summoner_ranked_solo_rank summoner_ranked_solo_rank VARCHAR(255) DEFAULT NULL, CHANGE summoner_ranked_solo_tier summoner_ranked_solo_tier VARCHAR(255) DEFAULT NULL, CHANGE summoner_ranked_solo_league_points summoner_ranked_solo_league_points VARCHAR(255) DEFAULT NULL, CHANGE summoner_ranked_solo_losses summoner_ranked_solo_losses VARCHAR(255) DEFAULT NULL, CHANGE summoner_level summoner_level INT DEFAULT NULL, CHANGE last_update last_update DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE riot_account ADD CONSTRAINT FK_79C2D42DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account DROP FOREIGN KEY FK_79C2D42DA76ED395');
        $this->addSql('DROP TABLE user');
        $this->addSql('ALTER TABLE riot_account ADD riot_puuid VARCHAR(255) NOT NULL, ADD summoner_ranked_solo_wins VARCHAR(255) NOT NULL, DROP puuid, DROP score, CHANGE user_id user_id INT NOT NULL, CHANGE riot_id riot_id VARCHAR(255) NOT NULL, CHANGE summoner_name summoner_name VARCHAR(255) NOT NULL, CHANGE summoner_ranked_solo_rank summoner_ranked_solo_rank VARCHAR(255) NOT NULL, CHANGE summoner_ranked_solo_tier summoner_ranked_solo_tier VARCHAR(255) NOT NULL, CHANGE summoner_ranked_solo_league_points summoner_ranked_solo_league_points INT NOT NULL, CHANGE summoner_ranked_solo_losses summoner_ranked_solo_losses VARCHAR(255) NOT NULL, CHANGE summoner_level summoner_level VARCHAR(255) NOT NULL, CHANGE last_update last_update VARCHAR(255) NOT NULL');
    }
}
