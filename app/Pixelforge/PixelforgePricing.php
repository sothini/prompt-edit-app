<?php

namespace App\Pixelforge;

use App\Pricing\PriceList;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PixelforgePricing
{
    public function current(): PriceList
    {
        /** @var array{rates: array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}, tokens: array{characters_per_text_token: int, text_token_variance: float, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>}} $pricing */
        $pricing = Cache::remember('pixelforge.pricing', now()->addHour(), fn () => Http::baseUrl((string) config('services.pixelforge.base_url'))
            ->acceptJson()
            ->timeout(5)
            ->retry(2, 200)
            ->get('pricing')
            ->throw()
            ->json());

        return PriceList::fromApi($pricing);
    }
}
