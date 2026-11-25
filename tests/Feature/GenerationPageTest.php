<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GenerationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('generations.create'))->assertRedirect(route('login'));
    }

    public function test_page_renders_with_size_and_quality_options(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('generations.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('generations/Create')
                ->has('sizes', 3)
                ->has('qualities', 3)
                ->where('maxReferenceImages', 4));
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('generations.store'), [
                'prompt' => '',
                'size' => '999x999',
                'quality' => 'ultra',
                'reference_images' => ['not-a-url'],
            ])
            ->assertSessionHasErrors(['prompt', 'size', 'quality', 'reference_images.0']);
    }

    public function test_more_than_four_reference_images_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('generations.store'), [
                'prompt' => 'A cat',
                'size' => '1024x1024',
                'quality' => 'medium',
                'reference_images' => array_fill(0, 5, 'https://example.com/a.png'),
            ])
            ->assertSessionHasErrors('reference_images');
    }
}
