<?php declare(strict_types=1);

namespace ImportExport\Command;

use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[AsCommand(
    name: 'import-export:run',
    description: 'Test command for import-export.',
)]
class NewImportExportCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('type', InputArgument::REQUIRED, 'Import/Export type.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // TODO Move the selection of profile into command argument
        $yaml = file_get_contents(__DIR__ . '/../../profiles/product_required.yaml');

        $profile = yaml_parse($yaml);

        $this->validateCsvAgainstSchema($profile);

        if ($input->getArgument('type') === 'export') {
            $this->export($profile);

            return Command::SUCCESS;
        }

        if ($input->getArgument('type') === 'import') {
            $this->import($profile);

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    private function export(array $profile): void
    {
        $serializeScript = $profile['serialize_script'];

        $loader = new ArrayLoader([
            'serializeScript' => $serializeScript,
        ]);

        $twig = new Environment($loader);

        $entities = $this->getEntities($profile)['data'];

        $stream = fopen(__DIR__ . '/../../export.csv', 'w+');

        // add headers from profile mappings
        $headers = array_map(fn($mapping) => $mapping['file_column'], $profile['mappings']);

        fputcsv($stream, $headers);

        foreach ($entities as $entity) {
            $row = [];

            foreach ($profile['mappings'] as $mapping) {
                if (isset($mapping['entity_path'])) {
                    // explode entity path to get potential nested values
                    $entityPath = explode('.', $mapping['entity_path']);

                    // if entity path has only one element, get the value directly
                    if (count($entityPath) === 1) {
                        $row[$mapping['entity_path']] = $entity[$mapping['entity_path']];

                        continue;
                    }

                    // if entity path has more than one element, get the nested value
                    $nestedValue = $entity;
                    foreach ($entityPath as $path) {
                        $nestedValue = $nestedValue[$path];
                    }

                    $row[$mapping['entity_path']] = $nestedValue;

                    continue;
                }

                // set empty value for key which should later be filled by the serialize script
                if (isset($mapping['key'])) {
                    $row[$mapping['key']] = '';
                }
            }

            $twig->render('serializeScript', [
                'entity' => $entity,
                'row' => &$row,
            ]);

            fputcsv($stream, $row);
        }

        fclose($stream);
    }

    private function import(array $profile): void
    {
        // TODO get csv via command argument
        $file = fopen(__DIR__ . '/../../export.csv', 'r');

        $headers = fgetcsv($file);

        $csv = [];
        while ($row = fgetcsv($file)) {
            $csv[] = array_combine($headers, $row);
        }

        // fetch array keys from profile mappings
        $keys = [];
        foreach ($profile['mappings'] as $mapping) {
            $keys[$mapping['file_column']] = $mapping['entity_path'] ?? $mapping['key'];
        }

        // replace keys in csv with keys from profile mappings
        foreach ($csv as &$row) {
            $row = array_combine(array_map(fn($key) => $keys[$key], array_keys($row)), $row);
        }

        $deserializeScript = $profile['deserialize_script'];

        $loader = new ArrayLoader([
            'deserializeScript' => $deserializeScript,
        ]);

        $twig = new Environment($loader);

        $payload = [];
        foreach ($csv as $row) {
            $entity = [];

            foreach ($row as $key => $value) {
                $mapping = $this->findMappingByEntityPath($key, $profile['mappings']);

                if ($mapping === null) {
                    continue;
                }

                // if not nested, set value directly
                if (!str_contains($mapping['entity_path'], '.')) {
                    $entity[$mapping['entity_path']] = $value;

                    continue;
                }

                // if nested, set value in nested array
                $entityPath = explode('.', $mapping['entity_path']);

                $nestedEntity = &$entity;
                foreach ($entityPath as $path) {
                    if (!isset($nestedEntity[$path])) {
                        $nestedEntity[$path] = [];
                    }

                    $nestedEntity = &$nestedEntity[$path];
                }

                $nestedEntity = $value;
            }

            $twig->render('deserializeScript', [
                'entity' => &$entity,
                'row' => $row,
            ]);

            $payload[] = $entity;
        }

        // TODO send payload to the API
        dd($payload);
    }

    /**
     * TODO do this via API
     */
    private function getEntities(array $profile): array
    {
        $manufacturer = [
            'id' => Uuid::randomHex(),
            'name' => 'Test Manufacturer',
        ];

        $euroCurrencyId = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';
        $usdCurrencyId = 'b7d2554b0ce847cd82f3ac9bd1c0dfcb';

        // create 5 mock product entities
        for ($i = 1; $i <= 5; $i++) {
            $netPrice = 10.00 * $i;
            $grossPrice = 15.00 * $i;
            $stock = rand(1, 100);

            $product = [
                'id' => Uuid::randomHex(),
                'productNumber' => 'SW0000' . $i,
                'name' => 'Test Product ' . $i,
                'stock' => $stock,
                'taxId' => Uuid::randomHex(),
                'price' => [
                    [
                        'currencyId' => $usdCurrencyId,
                        'net' => $netPrice,
                        'gross' => $grossPrice,
                        'linked' => false,
                    ],
                    [
                        'currencyId' => $euroCurrencyId,
                        'net' => $netPrice * 0.9,
                        'gross' => $grossPrice * 0.9,
                        'linked' => false,
                    ],
                ],
                'manufacturer' => [
                    'id' => $manufacturer['id'],
                    'name' => $manufacturer['name'],
                ],
            ];

            $entities[] = $product;
        }

        return [
            'data' => $entities,
        ];
    }

    private function validateCsvAgainstSchema(array $profile): void
    {
        $schema = file_get_contents(__DIR__ . '/../../../../../src/Administration/Resources/app/administration/test/_mocks_/entity-schema.json');

        $schema = json_decode($schema, true);

        $this->checkPathsForEntity($profile['entity'], $profile['mappings'], $schema);
    }


    private function checkPathsForEntity(string $entityName, array $mappings, array $schema): void
    {
        // check if entity exists in schema
        if (!isset($schema[$entityName])) {
            throw new \RuntimeException(sprintf('Entity %s not found in schema.', $entityName));
        }

        foreach ($mappings as $mapping) {
            if (!isset($mapping['entity_path'])) {
                continue;
            }

            $path = $mapping['entity_path'];
            $parts = explode('.', $path);
            $rootPath = $parts[0];

            // check if root path exists in schema
            if (!isset($schema[$entityName]['properties'][$rootPath])) {
                throw new \RuntimeException(sprintf('Path %s not found in %s.', $path, $entityName));
            }

            if (count($parts) > 1) {
                // if its multiple parts it should be an association
                if ($schema[$entityName]['properties'][$rootPath]['type'] !== 'association') {
                    throw new \RuntimeException(sprintf('Path %s in %s is not an association.', $path, $entityName));
                }

                $paths = array_slice($parts, 1);

                $entity = $schema[$entityName]['properties'][$rootPath]['entity'];
                $path = implode('.', $paths);

                $mapping = [
                    'entity_path' => $path,
                ];

                $this->checkPathsForEntity($entity, [$mapping], $schema);
            }
        }
    }

    private function findMappingByEntityPath(string $entityPath, array $mappings): ?array
    {
        foreach ($mappings as $mapping) {
            if (isset($mapping['entity_path']) && $mapping['entity_path'] === $entityPath) {
                return $mapping;
            }
        }

        return null;
    }
}
