<?php

namespace App\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

final readonly class PriceCalculator
{
    public function __construct(private int $markupPercent) {}

    /**
     * What Pixelforge charges us, in nanodollars.
     */
    public function providerCost(TokenUsage $usage, Rates $rates): int
    {
        return $usage->inputText * $rates->inputText
            + $usage->inputImage * $rates->inputImage
            + $usage->output * $rates->output
            + $usage->reasoning * $rates->reasoning;
    }

    /**
     * What we charge the user: provider cost plus markup, rounded half up to a whole nanodollar.
     */
    public function userPrice(int $providerCost): int
    {
        return intdiv($providerCost * (100 + $this->markupPercent) + 50, 100);
    }

    public function estimate(
        PriceList $prices,
        int $promptCharacters,
        ImageSize $size,
        ImageQuality $quality,
        int $referenceImages,
    ): Estimate {
        $perToken = $prices->charactersPerTextToken;
        $variance = $prices->textTokenVariancePercent;

        $textTokens = intdiv($promptCharacters + $perToken - 1, $perToken); // ceil(characters / 4)
        $imageTokens = $referenceImages * $prices->tokensPerReferenceImage;
        $outputTokens = $prices->outputTokens($quality, $size);
        $reasoning = $prices->reasoningTokens($quality);

        $min = new TokenUsage(
            inputText: intdiv($textTokens * (100 - $variance), 100),          // floor(-15%)
            inputImage: $imageTokens,
            output: $outputTokens,
            reasoning: $reasoning['min'],
        );

        $max = new TokenUsage(
            inputText: intdiv($textTokens * (100 + $variance) + 99, 100),     // ceil(+15%)
            inputImage: $imageTokens,
            output: $outputTokens,
            reasoning: $reasoning['max'],
        );

        $minCost = $this->providerCost($min, $prices->rates);
        $maxCost = $this->providerCost($max, $prices->rates);

        return new Estimate(
            minTokens: $min,
            maxTokens: $max,
            minProviderCost: $minCost,
            maxProviderCost: $maxCost,
            minPrice: $this->userPrice($minCost),
            maxPrice: $this->userPrice($maxCost),
        );
    }
}
