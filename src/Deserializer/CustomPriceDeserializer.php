<?php declare(strict_types=1);

namespace ImportExport\Deserializer;

class CustomPriceDeserializer implements Deserializer
{
    public function deserialize($value, $path = 'price'): array
    {
        return [
            $path => [
                [
                    'currencyId' => "b7d2554b0ce847cd82f3ac9bd1c0dfca",
                    'net' => $value['EUR.net'],
                    'gross' => $value['EUR.gross'],
                    'linked' => false,
                ],
                [
                    'currencyId' => "b7d2554b0ce847cd82f3ac9bd1c0dfcb",
                    'net' => $value['USD.net'],
                    'gross' => $value['USD.gross'],
                    'linked' => false,
                ]
            ],
        ];
    }
}
