<?php declare(strict_types=1);

namespace ImportExport\Deserializer;

interface Deserializer
{
    public function deserialize($data): mixed;
}
