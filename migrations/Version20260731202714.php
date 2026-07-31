<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration séparée du reste de l'enrichissement : le changement de type
 * modifie le JSON exposé au front ("30" devient 30) et doit rester
 * révocable indépendamment.
 */
final class Version20260731202714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'game_duration string → int (durée en minutes, valeurs déjà numériques)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE history_account_lol CHANGE game_duration game_duration INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE history_account_lol CHANGE game_duration game_duration VARCHAR(1000) NOT NULL');
    }
}
