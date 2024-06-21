<?php declare(strict_types=1);

namespace ImportExport\Deserializer;

class CustomTagDeserializer implements Deserializer
{
    public function deserialize($value, $path = 'tags'): array
    {
        // maybe fetch Uuid first and remember already created tags

        $tags = explode('|', $value);

        return [
            $path => array_map(function ($tag) {
                return [
                    'name' => $tag,
                ];
            }, $tags),
        ];
    }
}
