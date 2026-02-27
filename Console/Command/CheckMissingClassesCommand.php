<?php
/**
 * eWings eCommerce - https://ewings.be
 * Copyright © eWings eCommerce. All rights reserved.
 * This product is licensed per Magento install and only valid for eWings Customers.
 */
declare(strict_types=1);

namespace Perfcom\EavAttributeHelperCommands\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckMissingClassesCommand extends Command
{
    private const COLUMNS_TO_CHECK = [
        'attribute_model',
        'backend_model',
        'frontend_model',
        'source_model'
    ];

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('perfcom:eav:check-missing-classes');
        $this->setDescription('Check EAV attributes for missing classes and provide SQL to remove them');
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Checking EAV attributes for missing classes...</info>');
        $output->writeln('');

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('eav_attribute');

        // Build the WHERE clause to check for non-empty class columns
        $whereConditions = [];
        foreach (self::COLUMNS_TO_CHECK as $column) {
            $whereConditions[] = "({$column} IS NOT NULL AND {$column} != '')";
        }
        $whereClause = implode(' OR ', $whereConditions);

        // Fetch all attributes with class references
        $columns = implode(', ', array_merge(['attribute_id', 'attribute_code', 'entity_type_id'], self::COLUMNS_TO_CHECK));
        $sql = "SELECT {$columns}
                FROM {$tableName}
                WHERE {$whereClause}
                ORDER BY entity_type_id, attribute_code";

        $attributes = $connection->fetchAll($sql);

        if (empty($attributes)) {
            $output->writeln('<info>No attributes with class references found.</info>');
            return Cli::RETURN_SUCCESS;
        }

        $output->writeln(sprintf('<info>Found %d attributes with class references. Checking...</info>', count($attributes)));
        $output->writeln('');

        $missingClasses = [];
        $totalChecked = 0;

        foreach ($attributes as $attribute) {
            foreach (self::COLUMNS_TO_CHECK as $column) {
                $className = $attribute[$column] ?? null;

                if (empty($className)) {
                    continue;
                }

                $totalChecked++;

                // Check if class exists using class_exists
                if (!class_exists($className)) {
                    $missingClasses[] = [
                        'attribute_id' => $attribute['attribute_id'],
                        'attribute_code' => $attribute['attribute_code'],
                        'entity_type_id' => $attribute['entity_type_id'],
                        'column' => $column,
                        'class_name' => $className,
                    ];
                }
            }
        }

        if (empty($missingClasses)) {
            $output->writeln('<info>✓ All class references are valid! No missing classes found.</info>');
            $output->writeln(sprintf('<comment>Total class references checked: %d</comment>', $totalChecked));
            return Cli::RETURN_SUCCESS;
        }

        // Display results in a table
        $output->writeln(sprintf('<error>✗ Found %d missing class references:</error>', count($missingClasses)));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Attribute ID', 'Attribute Code', 'Entity Type ID', 'Column', 'Missing Class']);

        foreach ($missingClasses as $item) {
            $table->addRow([
                $item['attribute_id'],
                $item['attribute_code'],
                $item['entity_type_id'],
                $item['column'],
                $item['class_name'],
            ]);
        }

        $table->render();

        // Generate SQL queries to fix the issues
        $output->writeln('');
        $output->writeln('<info>SQL queries to remove missing class references:</info>');
        $output->writeln('');
        $output->writeln('<comment>-- Set missing class references to NULL</comment>');

        foreach ($missingClasses as $item) {
            $sql = sprintf(
                "UPDATE %s SET %s = NULL WHERE attribute_id = %d; -- %s (%s)",
                $tableName,
                $item['column'],
                $item['attribute_id'],
                $item['attribute_code'],
                $item['class_name']
            );
            $output->writeln($sql);
        }

        $output->writeln('');
        $output->writeln('<comment>-- Alternative: Delete entire attributes (use with caution!)</comment>');

        $attributeIds = array_unique(array_column($missingClasses, 'attribute_id'));
        $attributeIdsList = implode(', ', $attributeIds);

        $deleteSql = sprintf(
            "DELETE FROM %s WHERE attribute_id IN (%s);",
            $tableName,
            $attributeIdsList
        );
        $output->writeln($deleteSql);

        $output->writeln('');
        $output->writeln('<error>⚠ WARNING: Review and test these SQL queries before executing them on production!</error>');
        $output->writeln(sprintf('<comment>Total class references checked: %d</comment>', $totalChecked));
        $output->writeln(sprintf('<comment>Missing class references found: %d</comment>', count($missingClasses)));

        return Cli::RETURN_FAILURE;
    }
}

