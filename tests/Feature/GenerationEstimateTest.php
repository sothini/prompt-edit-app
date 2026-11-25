<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerationEstimateTest extends TestCase
{
    use RefreshDatabase;

    private function fakePricing(): void
    {
        Http::fake([
            '*/pricing' => Http::response(json_decode(
                (string) file_get_contents(base_path('tests/Fixtures/pixelforge/pricing.json')),
                true,
            )),
        ]);
    }

    public function test_it_returns_the_price_range_for_the_docs_example(): void
    {
        $this->fakePricing();

        $this->actingAs(User::factory()->create())
            ->getJson(route('generations.estimate', [
                'characters' => 400,
                'size' => '1536x1024',
                'quality' => 'high',
                'reference_images' => 2,
            ]))
            ->assertOk()
            ->assertJson([
                'max_price' => 467_343_750,      // $0.373875 provider max + 25%
                'markup_percent' => 25,
                'tokens' => ['input_image' => 1530, 'output' => 6450],
            ])
            ->assertJsonMissingPath('max_provider_cost');
    }

    public function test_it_reports_when_pricing_is_unavailable(): void
    {
        Http::fake(['*/pricing' => Http::response(null, 500)]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('generations.estimate', [
                'characters' => 10,
                'size' => '1024x1024',
                'quality' => 'low',
                'reference_images' => 0,
            ]))
            ->assertStatus(503);
    }

    public function test_it_validates_the_options(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('generations.estimate', ['characters' => -1, 'size' => 'huge']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['characters', 'size', 'quality', 'reference_images']);
    }

    public function test_guests_cannot_get_estimates(): void
    {
        $this->getJson(route('generations.estimate'))->assertUnauthorized();
    }
}
