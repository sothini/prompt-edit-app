<?php

namespace App\Pricing;

use BcMath\Number;
use UnexpectedValueException;

final readonly class Rates
{
    /**
     * All values are nanodollars per token.
     */
    public function __construct(
        public int $inputText,
        public int $inputImage,
        public int $output,
        public int $reasoning,
    ) {}

    /**
     * Pixelforge publishes rates as decimal strings in USD per 1M tokens.
     *
     * @param  array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}  $rates
     */
    public static function fromUsdPerMillion(array $rates): self
    {
        return new self(
            self::nanosPerToken($rates['input_text_tokens']),
            self::nanosPerToken($rates['input_image_tokens']),
            self::nanosPerToken($rates['output_tokens']),
            self::nanosPerToken($rates['reasoning_tokens']),
        );
    }

    /**
     * $X per 1M tokens = X * 1e9 / 1e6 = X * 1000 nanodollars per token.
     */
    private static function nanosPerToken(string $usdPerMillion): int
    {
        if (! is_numeric($usdPerMillion)) {
            throw new UnexpectedValueException("Rate [{$usdPerMillion}] is not a number.");
        }

        $nanos = (new Number($usdPerMillion))->mul(1000);

        if ($nanos->compare($nanos->round(0)) !== 0) {
            throw new UnexpectedValueException("Rate {$usdPerMillion} is not a whole number of nanodollars per token.");
        }

        return (int) $nanos->round(0)->value;
    }
}
