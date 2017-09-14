<?php

namespace Nathejk\Migration;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170914190736 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE qr (id INT UNSIGNED AUTO_INCREMENT NOT NULL, number INT UNSIGNED DEFAULT NULL, secret VARCHAR(20) NOT NULL, mapCreateTime DATETIME DEFAULT NULL, mapCreateByPhone VARCHAR(20) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scan (id INT UNSIGNED AUTO_INCREMENT NOT NULL, qr_id INT UNSIGNED DEFAULT NULL, phone VARCHAR(20) NOT NULL, time DATETIME NOT NULL, location VARCHAR(100) DEFAULT NULL, INDEX IDX_C4B3B3AE5AA64A57 (qr_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE scan ADD CONSTRAINT FK_C4B3B3AE5AA64A57 FOREIGN KEY (qr_id) REFERENCES qr (id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE scan DROP FOREIGN KEY FK_C4B3B3AE5AA64A57');
        $this->addSql('DROP TABLE qr');
        $this->addSql('DROP TABLE scan');
    }
}
