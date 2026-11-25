<?php

namespace Tests\Unit\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Pricing\PriceCalculator;
use App\Pricing\PriceList;
use App\Pricing\Rates;
use App\Pricing\TokenUsage;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    private PriceList $prices;

    protected function setUp(): void
    {
        $this->calculator = new PriceCalculator(markupPercent: 25);
        $this->prices = PriceList::fromApi([
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => 0.15,
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ]);
    }

    public function test_rates_are_converted_to_nanodollars_per_token(): void
    {
        $rates = $this->prices->rates;

        $this->assertSame([5_000, 10_000, 40_000, 12_500], [
            $rates->inputText, $rates->inputImage, $rates->output, $rates->reasoning,
        ]);
    }

    public function test_rates_finer_than_a_nanodollar_are_rejected(): void
    {
        $this->expectException(UnexpectedValueException::class);

        Rates::fromUsdPerMillion([
            'input_text_tokens' => '5.0001',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ]);
    }

    public function test_provider_cost_matches_the_usage_example_in_the_docs(): void
    {
        // Docs: 41 text + 3300 output + 2950 reasoning tokens = "0.169080000"
        $usage = new TokenUsage(inputText: 41, inputImage: 0, output: 3300, reasoning: 2950);

        $this->assertSame(169_080_000, $this->calculator->providerCost($usage, $this->prices->rates));
    }

    public function test_user_price_adds_the_markup(): void
    {
        $this->assertSame(211_350_000, $this->calculator->userPrice(169_080_000));
    }

    public function test_user_price_rounds_half_up_to_a_whole_nanodollar(): void
    {
        $this->assertSame(1, $this->calculator->userPrice(1));   // 1.25 -> 1
        $this->assertSame(3, $this->calculator->userPrice(2));   // 2.50 -> 3
        $this->assertSame(0, $this->calculator->userPrice(0));
    }

    public function test_maximum_estimate_matches_the_worked_example_in_the_docs(): void
    {
        // Docs: high, 1536x1024, 400-character prompt, 2 reference images => max $0.373875
        $estimate = $this->calculator->estimate($this->prices, 400, ImageSize::Landscape, ImageQuality::High, 2);

        $this->assertSame(115, $estimate->maxTokens->inputText);
        $this->assertSame(1_530, $estimate->maxTokens->inputImage);
        $this->assertSame(373_875_000, $estimate->maxProviderCost);
        $this->assertSame(467_343_750, $estimate->maxPrice);
    }

    public function test_minimum_estimate_uses_the_lower_bounds(): void
    {
        $estimate = $this->calculator->estimate($this->prices, 400, ImageSize::Landscape, ImageQuality::High, 2);

        $this->assertSame(85, $estimate->minTokens->inputText);
        $this->assertSame(1_000, $estimate->minTokens->reasoning);
        // 85*5000 + 1530*10000 + 6450*40000 + 1000*12500
        $this->assertSame(286_225_000, $estimate->minProviderCost);
    }

    public function test_text_tokens_round_up_to_a_whole_token(): void
    {
        $estimate = $this->calculator->estimate($this->prices, 1, ImageSize::Square, ImageQuality::Low, 0);

        $this->assertSame(2, $estimate->maxTokens->inputText); // ceil(1/4)=1, then ceil(1 * 1.15) = 2
        $this->assertSame(0, $estimate->minTokens->inputText); // floor(1 * 0.85) = 0
    }
}
