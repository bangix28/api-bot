<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230129135119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account DROP FOREIGN KEY FK_79C2D42DA76ED395');
        $this->addSql('DROP INDEX UNIQ_79C2D42DA76ED395 ON riot_account');
        $this->addSql('ALTER TABLE riot_account DROP user_id, CHANGE riot_id riot_id VARCHAR(255) DEFAULT NULL, CHANGE riot_puuid riot_puuid VARCHAR(255) DEFAULT NULL, CHANGE summoner_name summoner_name VARCHAR(255) DEFAULT NULL, CHANGE summoner_ranked_solo_rank summoner_ranked_solo_rank VARCHAR(255) DEFAULT NULL, CHANGE summoner_ranked_solo_tier summoner_ranked_solo_tier VARCHAR(255) DEFAULT NULL, CHANGE summoner_ranked_solo_league_points summoner_ranked_solo_league_points INT DEFAULT NULL, CHANGE summoner_ranked_solo_wins summoner_ranked_solo_wins VARCHAR(255) DEFAULT NULL, CHANGE summoner_ranked_solo_losses summoner_ranked_solo_losses VARCHAR(255) DEFAULT NULL, CHANGE summoner_level summoner_level VARCHAR(255) DEFAULT NULL, CHANGE last_update last_update VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649C6EAB37D');
        $this->addSql('DROP INDEX UNIQ_8D93D649C6EAB37D ON user');
        $this->addSql('ALTER TABLE user DROP riot_account_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE riot_account ADD user_id INT DEFAULT NULL, CHANGE riot_id riot_id VARCHAR(255) NOT NULL, CHANGE riot_puuid riot_puuid VARCHAR(255) NOT NULL, CHANGE summoner_name summoner_name VARCHAR(255) NOT NULL, CHANGE summoner_ranked_solo_rank summoner_ranked_solo_rank VARCHAR(255) NOT NULL, CHANGE summoner_ranked_solo_tier summoner_ranked_solo_tier VARCHAR(255) NOT NULL, CHANGE summoner_ranked_solo_league_points summoner_ranked_solo_league_points INT NOT NULL, CHANGE summoner_ranked_solo_wins summoner_ranked_solo_wins VARCHAR(255) NOT NULL, CHANGE summoner_ranked_solo_losses summoner_ranked_solo_losses VARCHAR(255) NOT NULL, CHANGE summoner_level summoner_level VARCHAR(255) NOT NULL, CHANGE last_update last_update VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE riot_account ADD CONSTRAINT FK_79C2D42DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_79C2D42DA76ED395 ON riot_account (user_id)');
        $this->addSql('ALTER TABLE user ADD riot_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649C6EAB37D FOREIGN KEY (riot_account_id) REFERENCES riot_account (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649C6EAB37D ON user (riot_account_id)');
    }
}
