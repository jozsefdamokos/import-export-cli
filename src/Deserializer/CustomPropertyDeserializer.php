<?php declare(strict_types=1);

namespace ImportExport\Deserializer;

class CustomPropertyDeserializer implements Deserializer
{
    public function deserialize($value, $path = 'properties'): array
    {
        // fetch id by name
        $id = 'property-id';

        return [
            $path => [
                [
                    'id' => $id,
                    'position' => $value['position'],
                ],
            ],
        ];
    }
}
