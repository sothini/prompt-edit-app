<?php

namespace App\Http\Controllers;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Http\Requests\EstimateGenerationRequest;
use App\Http\Requests\StoreGenerationRequest;
use App\Pixelforge\PixelforgePricing;
use App\Pricing\PriceCalculator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('generations/Create', [
            'sizes' => array_map(fn (ImageSize $size) => [
                'value' => $size->value,
                'label' => $size->label(),
                'width' => $size->width(),
                'height' => $size->height(),
            ], ImageSize::cases()),
            'qualities' => array_map(fn (ImageQuality $quality) => [
                'value' => $quality->value,
                'label' => $quality->label(),
                'description' => $quality->description(),
            ], ImageQuality::cases()),
            'maxPromptLength' => StoreGenerationRequest::MAX_PROMPT_LENGTH,
            'maxReferenceImages' => StoreGenerationRequest::MAX_REFERENCE_IMAGES,
        ]);
    }

    public function store(StoreGenerationRequest $request): RedirectResponse
    {
        // Step 5: create the generation and dispatch the submit job.
        return back();
    }

    public function estimate(
        EstimateGenerationRequest $request,
        PixelforgePricing $pricing,
        PriceCalculator $calculator,
    ): JsonResponse {
        try {
            $prices = $pricing->current();
        } catch (ConnectionException|RequestException) {
            return response()->json(['message' => 'Pricing is temporarily unavailable.'], 503);
        }

        $estimate = $calculator->estimate(
            $prices,
            $request->integer('characters'),
            ImageSize::from($request->string('size')->value()),
            ImageQuality::from($request->string('quality')->value()),
            $request->integer('reference_images'),
        );

        return response()->json([
            'min_price' => $estimate->minPrice,
            'max_price' => $estimate->maxPrice,
            'markup_percent' => (int) config('services.pixelforge.markup_percent'),
            'tokens' => [
                'input_text' => ['min' => $estimate->minTokens->inputText, 'max' => $estimate->maxTokens->inputText],
                'input_image' => $estimate->maxTokens->inputImage,
                'output' => $estimate->maxTokens->output,
                'reasoning' => ['min' => $estimate->minTokens->reasoning, 'max' => $estimate->maxTokens->reasoning],
            ],
        ]);
    }
}
