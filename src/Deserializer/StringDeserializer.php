<?php declare(strict_types=1);

namespace ImportExport\Deserializer;

class StringDeserializer implements Deserializer
{
    public function deserialize($data): string
    {
        return (string) $data;
    }
}
