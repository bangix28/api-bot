<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260731202713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enrichissement match (socle, build, stats avancées) + ranked flags/flex, index unique d\'idempotence';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE history_account_lol ADD match_id VARCHAR(64) DEFAULT NULL, ADD queue_id INT DEFAULT NULL, ADD champion_name VARCHAR(50) DEFAULT NULL, ADD team_position VARCHAR(10) DEFAULT NULL, ADD champ_level INT DEFAULT NULL, ADD creep_score INT DEFAULT NULL, ADD vision_score INT DEFAULT NULL, ADD gold_earned INT DEFAULT NULL, ADD item0 INT DEFAULT NULL, ADD item1 INT DEFAULT NULL, ADD item2 INT DEFAULT NULL, ADD item3 INT DEFAULT NULL, ADD item4 INT DEFAULT NULL, ADD item5 INT DEFAULT NULL, ADD item6 INT DEFAULT NULL, ADD summoner_spell1_id INT DEFAULT NULL, ADD summoner_spell2_id INT DEFAULT NULL, ADD rune_keystone_id INT DEFAULT NULL, ADD rune_primary_style_id INT DEFAULT NULL, ADD rune_sub_style_id INT DEFAULT NULL, ADD rune_stat_defense INT DEFAULT NULL, ADD rune_stat_flex INT DEFAULT NULL, ADD rune_stat_offense INT DEFAULT NULL, ADD total_damage_dealt_to_champions INT DEFAULT NULL, ADD total_damage_taken INT DEFAULT NULL, ADD double_kills SMALLINT DEFAULT NULL, ADD triple_kills SMALLINT DEFAULT NULL, ADD quadra_kills SMALLINT DEFAULT NULL, ADD penta_kills SMALLINT DEFAULT NULL, ADD first_blood_kill TINYINT DEFAULT NULL, ADD game_ended_in_surrender TINYINT DEFAULT NULL, ADD kda DOUBLE PRECISION DEFAULT NULL, ADD kill_participation DOUBLE PRECISION DEFAULT NULL, ADD damage_per_minute DOUBLE PRECISION DEFAULT NULL, ADD gold_per_minute DOUBLE PRECISION DEFAULT NULL, ADD vision_score_per_minute DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_match_per_account ON history_account_lol (match_id, riot_account_id)');
        $this->addSql('ALTER TABLE riot_account ADD solo_hot_streak TINYINT DEFAULT NULL, ADD solo_veteran TINYINT DEFAULT NULL, ADD solo_fresh_blood TINYINT DEFAULT NULL, ADD solo_mini_series_wins INT DEFAULT NULL, ADD solo_mini_series_losses INT DEFAULT NULL, ADD solo_mini_series_target INT DEFAULT NULL, ADD solo_mini_series_progress VARCHAR(5) DEFAULT NULL, ADD summoner_ranked_flex_tier VARCHAR(255) DEFAULT NULL, ADD summoner_ranked_flex_rank VARCHAR(255) DEFAULT NULL, ADD summoner_ranked_flex_league_points INT DEFAULT NULL, ADD summoner_ranked_flex_wins INT DEFAULT NULL, ADD summoner_ranked_flex_losses INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_match_per_account ON history_account_lol');
        $this->addSql('ALTER TABLE history_account_lol DROP match_id, DROP queue_id, DROP champion_name, DROP team_position, DROP champ_level, DROP creep_score, DROP vision_score, DROP gold_earned, DROP item0, DROP item1, DROP item2, DROP item3, DROP item4, DROP item5, DROP item6, DROP summoner_spell1_id, DROP summoner_spell2_id, DROP rune_keystone_id, DROP rune_primary_style_id, DROP rune_sub_style_id, DROP rune_stat_defense, DROP rune_stat_flex, DROP rune_stat_offense, DROP total_damage_dealt_to_champions, DROP total_damage_taken, DROP double_kills, DROP triple_kills, DROP quadra_kills, DROP penta_kills, DROP first_blood_kill, DROP game_ended_in_surrender, DROP kda, DROP kill_participation, DROP damage_per_minute, DROP gold_per_minute, DROP vision_score_per_minute');
        $this->addSql('ALTER TABLE riot_account DROP solo_hot_streak, DROP solo_veteran, DROP solo_fresh_blood, DROP solo_mini_series_wins, DROP solo_mini_series_losses, DROP solo_mini_series_target, DROP solo_mini_series_progress, DROP summoner_ranked_flex_tier, DROP summoner_ranked_flex_rank, DROP summoner_ranked_flex_league_points, DROP summoner_ranked_flex_wins, DROP summoner_ranked_flex_losses');
    }
}
