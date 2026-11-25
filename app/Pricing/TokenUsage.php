<?php

namespace App\Pricing;

final readonly class TokenUsage
{
    public function __construct(
        public int $inputText,
        public int $inputImage,
        public int $output,
        public int $reasoning,
    ) {}

    /**
     * @param  array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int}  $usage
     */
    public static function fromApi(array $usage): self
    {
        return new self(
            $usage['input_text_tokens'],
            $usage['input_image_tokens'],
            $usage['output_tokens'],
            $usage['reasoning_tokens'],
        );
    }
}
