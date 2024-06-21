<?php declare(strict_types=1);

namespace ImportExport\Command;

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
    ];

    private array $profile = [
        'entity' => 'product',
        'mappings' => [
            [
                'header' => 'Product ID',
                'path' => 'id',
                'type' => 'string',
            ],
            [
                'header' => 'Product name',
                'path' => 'name',
                'type' => 'string',
            ],
            [
                'header' => 'Price (NET)',
                'path' => 'price.EUR.net',
                'type' => 'custom',
                'deserializer' => 'ImportExport\Deserializer\CustomPriceDeserializer',
            ],
        ],
    ];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // get csv from path
        $file = fopen(__DIR__ . '/../../products.csv', 'r');

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
                $value = $row[$mapping['header']];

                $deserializer = $mapping['type'] === 'custom' ? new $mapping['deserializer']() : new $this->deserializers[$mapping['type']]();

                $value = $deserializer->deserialize($value);

                $deserializedRow[$mapping['path']] = $value;
            }

            $deserialized[] = $deserializedRow;
        }

        dd($deserialized);
    }
}
