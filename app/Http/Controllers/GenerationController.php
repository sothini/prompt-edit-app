<?php

namespace App\Http\Controllers;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Http\Requests\StoreGenerationRequest;
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
}
