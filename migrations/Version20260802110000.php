<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les événements Ranked Race sont créés par les admins (EasyAdmin) avec des
 * dates libres, contrairement aux courses calendaires calculées à la volée.
 * Le seuil winrate est propre à chaque événement (5/15 n'ont de sens que pour
 * les fenêtres hebdo/mensuelles).
 */
final class Version20260802110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Événements Ranked Race créés par les admins (nom, fenêtre, file, seuil winrate)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE ranked_race_event (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                queue_type VARCHAR(20) NOT NULL,
                min_games_to_qualify INT NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ranked_race_event');
    }
}
