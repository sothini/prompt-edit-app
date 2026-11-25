<?php

namespace App\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

final readonly class PriceList
{
    /**
     * @param  array<string, array<string, int>>  $outputTokens  quality => size => tokens
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens  quality => range
     */
    public function __construct(
        public Rates $rates,
        public int $charactersPerTextToken,
        public int $textTokenVariancePercent,
        public int $tokensPerReferenceImage,
        private array $outputTokens,
        private array $reasoningTokens,
    ) {}

    /**
     * @param  array{
     *     rates: array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string},
     *     tokens: array{
     *         characters_per_text_token: int,
     *         text_token_variance: float,
     *         per_reference_image: int,
     *         output: array<string, array<string, int>>,
     *         reasoning: array<string, array{min: int, max: int}>,
     *     },
     * }  $pricing
     */
    public static function fromApi(array $pricing): self
    {
        return new self(
            rates: Rates::fromUsdPerMillion($pricing['rates']),
            charactersPerTextToken: $pricing['tokens']['characters_per_text_token'],
            textTokenVariancePercent: (int) round($pricing['tokens']['text_token_variance'] * 100),
            tokensPerReferenceImage: $pricing['tokens']['per_reference_image'],
            outputTokens: $pricing['tokens']['output'],
            reasoningTokens: $pricing['tokens']['reasoning'],
        );
    }

    public function outputTokens(ImageQuality $quality, ImageSize $size): int
    {
        return $this->outputTokens[$quality->value][$size->value];
    }

    /**
     * @return array{min: int, max: int}
     */
    public function reasoningTokens(ImageQuality $quality): array
    {
        return $this->reasoningTokens[$quality->value];
    }
}
