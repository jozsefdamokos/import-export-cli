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
        'string' => StringDeserializer::class,
        'price' => PriceDeserializer::class,
    ];

    private array $profile = [
        'entity' => 'product',
        'mappings' => [
            [
                'header' => 'Product ID',
                'path' => 'id',
                'type' => 'string',
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
            [
                'pathMapping' => [
                    'name' => ['Tag1', 'Tag2', 'Tag3'],
                ],
                'type' => 'custom',
                'path' => 'tags',
                'deserializer' => 'ImportExport\Deserializer\CustomTagsDeserializer',
            ],
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
        $file = fopen(__DIR__ . '/../../tags2.csv', 'r');

        $csv = [];

        // read headers of csv then create associative array
        $headers = fgetcsv($file);

        while ($row = fgetcsv($file)) {
            $csv[] = array_combine($headers, $row);
        }

        // deserialize csv data
        $deserialized = [];

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
}
