<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create employees table
 */
final class Version20240101120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create employees table for storing employee data from multiple providers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE employees_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE employees (
            id INT NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone_number VARCHAR(20) DEFAULT NULL,
            date_of_birth DATE DEFAULT NULL,
            hire_date DATE DEFAULT NULL,
            department VARCHAR(100) DEFAULT NULL,
            position VARCHAR(100) DEFAULT NULL,
            provider VARCHAR(50) NOT NULL,
            external_id VARCHAR(255) DEFAULT NULL,
            raw_data JSON DEFAULT NULL,
            track_tik_id INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BA82C300E7927C74 ON employees (email)');
        $this->addSql('CREATE INDEX IDX_BA82C30092C4739C ON employees (provider)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BA82C30092C4739C9F75D7B0 ON employees (provider, external_id)');
        $this->addSql('CREATE INDEX IDX_BA82C300A76ED395 ON employees (track_tik_id)');
        $this->addSql('COMMENT ON COLUMN employees.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN employees.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE employees_id_seq CASCADE');
        $this->addSql('DROP TABLE employees');
    }
}