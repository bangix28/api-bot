<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230130144350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE riot_account (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, riot_id VARCHAR(255) DEFAULT NULL, puuid VARCHAR(255) DEFAULT NULL, summoner_name VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_rank VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_tier VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_league_points VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_losses VARCHAR(255) DEFAULT NULL, summoner_level INT DEFAULT NULL, score INT DEFAULT NULL, last_update DATE DEFAULT NULL, UNIQUE INDEX UNIQ_79C2D42DA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE riot_account ADD CONSTRAINT FK_79C2D42DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account DROP FOREIGN KEY FK_79C2D42DA76ED395');
        $this->addSql('DROP TABLE riot_account');
    }
}
