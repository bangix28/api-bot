<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250115201148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE data_challenge (id INT AUTO_INCREMENT NOT NULL, queue_type INT NOT NULL, challenge VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE history_account_lol (id INT AUTO_INCREMENT NOT NULL, riot_account_id INT NOT NULL, data JSON DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C63CEF7AC6EAB37D (riot_account_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE riot_account (id INT AUTO_INCREMENT NOT NULL, riot_id VARCHAR(255) NOT NULL, puuid VARCHAR(255) DEFAULT NULL, summoner_name VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_rank VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_tier VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_league_points VARCHAR(255) DEFAULT NULL, summoner_ranked_solo_losses VARCHAR(255) DEFAULT NULL, summoner_level INT DEFAULT NULL, score INT DEFAULT NULL, last_update DATETIME DEFAULT NULL, summoner_ranked_solo_wins INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE history_account_lol ADD CONSTRAINT FK_C63CEF7AC6EAB37D FOREIGN KEY (riot_account_id) REFERENCES riot_account (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE history_account_lol DROP FOREIGN KEY FK_C63CEF7AC6EAB37D');
        $this->addSql('DROP TABLE data_challenge');
        $this->addSql('DROP TABLE history_account_lol');
        $this->addSql('DROP TABLE riot_account');
        $this->addSql('DROP TABLE `user`');
    }
}
