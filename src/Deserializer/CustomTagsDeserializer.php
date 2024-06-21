<?php declare(strict_types=1);

namespace ImportExport\Deserializer;

class CustomTagsDeserializer implements Deserializer
{
    public function deserialize($value, $path = 'tags'): array
    {
        // maybe fetch Uuid first and remember already created tags

        return [
            $path => array_map(function ($tag) {
                return [
                    'name' => $tag,
                ];
            }, $value['name']),
        ];
    }
}
