<?php declare(strict_types=1);

namespace ImportExport\Command;

use ImportExport\Deserializer\PriceDeserializer;
use ImportExport\Deserializer\StringDeserializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import-export:run',
    description: 'Test command for import-export.',
)]
class NewImportExportCommand extends Command
{
    /**
     * @var array<string, class-string>
     */
    private array $deserializers = [
        'uuid' => StringDeserializer::class,
        'string' => StringDeserializer::class,
        'boolean' => StringDeserializer::class,
        'int' => StringDeserializer::class,
        'float' => StringDeserializer::class,
        'price' => PriceDeserializer::class,
    ];

    private array $profile = [
        'entity' => 'product',
        'mappings' => [
            [
                'header' => 'Product ID',
                'path' => 'id',
                'type' => 'uuid',
            ],
            [
                'header' => 'Product number',
                'path' => 'productNumber',
                'type' => 'string',
            ],
            [
                'header' => 'Product name',
                'path' => 'translations.ENG.name',
                'type' => 'string',
            ],
            [
                'header' => 'Active',
                'path' => 'active',
                'type' => 'boolean',
            ],
            [
                'header' => 'Stock',
                'path' => 'stock',
                'type' => 'int',
            ],
            [
                'header' => 'Category',
                'path' => 'categories.name',
                'type' => 'string',
            ],
            [
                'header' => 'Manufacturer',
                'path' => 'manufacturer.translations.ENG.name',
                'type' => 'string',
            ],
            [
                'header' => 'Delivery time',
                'path' => 'deliveryTime.translations.ENG.name',
                'type' => 'string',
            ],
            [
                'header' => 'Tax rate',
                'path' => 'tax.taxRate',
                'type' => 'float',
            ],
            [
                'header' => 'Price EUR (NET)',
                'path' => 'price.EUR.net',
                'type' => 'float',
            ],
            // [
            //     'pathMapping' => [
            //         'name' => 'Property name',
            //         'position' => 'Property position',
            //     ],
            //     'type' => 'custom',
            //     'path' => 'properties',
            //     'deserializer' => 'ImportExport\Deserializer\CustomPropertyDeserializer',
            // ],
            // [
            //     'header' => 'Tags',
            //     'type' => 'custom',
            //     'path' => 'tags',
            //     'deserializer' => 'ImportExport\Deserializer\CustomTagDeserializer',
            // ],
            // [
            //     'header' => 'Product name',
            //     'path' => 'name',
            //     'type' => 'string',
            // ],
            // [
            //     'pathMapping' => [
            //         'EUR.net' => 'Price EUR (NET)',
            //         'EUR.gross' => 'Price EUR (GROSS)',
            //         'USD.net' => 'Price USD (NET)',
            //         'USD.gross' => 'Price USD (GROSS)'
            //     ],
            //     'type' => 'custom',
            //     'path' => 'price',
            //     'deserializer' => 'ImportExport\Deserializer\CustomPriceDeserializer',
            // ],
            // [
            //     'pathMapping' => [
            //         'name' => ['Tag1', 'Tag2', 'Tag3'],
            //     ],
            //     'type' => 'custom',
            //     'path' => 'tags',
            //     'deserializer' => 'ImportExport\Deserializer\CustomTagsDeserializer',
            // ],
            // [
            //     'pathMapping' => [
            //         'EUR.net' => 'Price EUR (NET)',
            //         'EUR.gross' => 'Price EUR (GROSS)',
            //         'USD.net' => 'Price USD (NET)',
            //         'USD.gross' => 'Price USD (GROSS)'
            //     ],
            //     'type' => 'price',
            //     'path' => 'price',
            // ],
            // [
            //     'pathMapping' => [
            //
            //     ],
            //     'type' => 'custom',
            //     'path' => 'custom.whatever',
            //     'deserializer' => 'ImportExport\Deserializer\CustomPriceDeserializer',
            // ],
            // [
            //     'header' => 'Test header',
            //     'type' => 'custom',
            //     'path' => 'custom.whatever',
            //     'deserializer' => 'ImportExport\Deserializer\CustomPriceDeserializer',
            // ],
        ],
    ];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // get csv from path
        $file = fopen(__DIR__ . '/../../products-to-validate.csv', 'r');

        $csv = [];

        // read headers of csv then create associative array
        $headers = fgetcsv($file);

        while ($row = fgetcsv($file)) {
            $csv[] = array_combine($headers, $row);
        }

        // deserialize csv data
        $deserialized = [];

        $this->validateCsvAgainstSchema();

        foreach ($csv as $row) {
            $deserializedRow = [];

            foreach ($this->profile['mappings'] as $mapping) {
                if ($mapping['type'] === 'custom') {

                    $deserializer = new $mapping['deserializer']();

                    if (isset($mapping['pathMapping'])) {
                        $value = [];
                        foreach ($mapping['pathMapping'] as $key => $header) {
                            if (\is_array($header)) {
                                $value[$key] = [];
                                foreach ($header as $headerKey => $headerValue) {
                                    $value[$key][$headerKey] = $row[$headerValue];
                                }

                                continue;
                            }

                            $value[$key] = $row[$header];
                        }

                        $value = $deserializer->deserialize($value);
                    } else {
                        $value = $row[$mapping['header']];

                        $value = $deserializer->deserialize($value);
                    }

                    $deserializedRow = array_merge_recursive($deserializedRow, $value);
                } else {
                    $deserializer = new $this->deserializers[$mapping['type']]();

                    $value = $row[$mapping['header']];

                    $value = $deserializer->deserialize($value);

                    $deserializedRow[$mapping['path']] = $value;
                }
            }

            $deserialized[] = $deserializedRow;
        }

        dd($deserialized);
    }

    private function validateCsvAgainstSchema(): void
    {
        // get entity schema json
        $schema = file_get_contents(__DIR__ . '/../../../../../src/Administration/Resources/app/administration/test/_mocks_/entity-schema.json');

        // json decode schema
        $schema = json_decode($schema, true);

        // validate csv against schema

        $entity = $this->profile['entity'];

        $this->checkPathsForEntity($entity, $this->profile['mappings'], $schema);
    }


    private function checkPathsForEntity(string $entityName, array $mappings, array $schema): void
    {
        // check if entity exists in schema
        if (!isset($schema[$entityName])) {
            throw new \RuntimeException(sprintf('Entity %s not found in schema.', $entityName));
        }

        foreach ($mappings as $mapping) {
            if ($mapping['type'] === 'custom') {
                continue;
            }

            $path = $mapping['path'];

            $parts = explode('.', $path);

            // check if first path exists in schema
            if (!isset($schema[$entityName]['properties'][$parts[0]])) {
                throw new \RuntimeException(sprintf('Path %s not found in %s.', $path, $entityName));
            }

            if (count($parts) === 1) {
                // check if type matches schema type
                if ($schema[$entityName]['properties'][$parts[0]]['type'] !== $mapping['type']) {
                    throw new \RuntimeException(sprintf(
                        'Type %s does not match schema type %s for %s in %s',
                        $mapping['type'],
                        $schema[$entityName]['properties'][$parts[0]]['type'],
                        $parts[0],
                        $entityName
                    ));
                }
            } else {
                // if its multiple parts it should be an association or json
                if ($schema[$entityName]['properties'][$parts[0]]['type'] !== 'association' && $schema[$entityName]['properties'][$parts[0]]['type'] !== 'json_object') {
                    throw new \RuntimeException(sprintf('Path %s in %s is not an association.', $path, $entityName));
                }

                // we skip json here because we don't know the structure of it, so we cannot validate
                if ($schema[$entityName]['properties'][$parts[0]]['type'] === 'json_object') {
                    continue;
                }

                $paths = array_slice($parts, 1);

                // we skip the language iso code for translations
                if ($parts[0] === 'translations') {
                    // todo validate that it's a valid iso code

                    $paths = array_slice($paths, 1);
                }

                $entity = $schema[$entityName]['properties'][$parts[0]]['entity'];
                $path = implode('.', $paths);

                $mapping = [
                    'path' => $path,
                    'type' => $mapping['type'],
                ];

                $this->checkPathsForEntity($entity, [$mapping], $schema);
            }
        }
    }
}
