<?php declare(strict_types=1);

namespace ImportExport\Deserializer;

class CustomPriceDeserializer implements Deserializer
{
    public function deserialize($data): float
    {
        return (float) $data * 1.19;
    }
}
