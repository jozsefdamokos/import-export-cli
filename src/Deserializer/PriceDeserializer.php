<?php declare(strict_types=1);

namespace ImportExport\Deserializer;

class PriceDeserializer implements Deserializer
{
    private array $currencyMapping = [
        'EUR' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
        'USD' => 'b7d2554b0ce847cd82f3ac9bd1c0dfcb',
    ];

    public function deserialize($pathMapping, $path = 'price'): array
    {
        $prices = [];

        foreach ($pathMapping as $childPath => $value) {
            $currency = explode('.', $childPath)[0];
            $pathz = explode('.', $childPath)[1];

            // $prices[$currency]...
        }

        return [$path => $prices];
    }
}
