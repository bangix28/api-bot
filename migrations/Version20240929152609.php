<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240929152609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE riot_account (id INT AUTO_INCREMENT NOT NULL, riot_id VARCHAR(255) NOT NULL, puuid VARCHAR(255) DEFAULT NULL, summoner_name VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_rank VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_tier VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_league_points VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_losses VARCHAR(255) DEFAULT NULL, summoner_level INT DEFAULT NULL, score INT DEFAULT NULL, last_update DATE DEFAULT NULL, summoner_ranked_solo_wins INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, discord_id VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE riot_account');
        $this->addSql('DROP TABLE user');
    }
}
