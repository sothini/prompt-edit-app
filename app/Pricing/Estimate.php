<?php

namespace App\Pricing;

final readonly class Estimate
{
    public function __construct(
        public TokenUsage $minTokens,
        public TokenUsage $maxTokens,
        public int $minProviderCost,
        public int $maxProviderCost,
        public int $minPrice,
        public int $maxPrice,
    ) {}
}
