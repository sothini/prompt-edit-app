<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ImagePlus, Sparkles, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    create,
    estimate as estimateRoute,
    store,
} from '@/actions/App/Http/Controllers/GenerationController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { watchDebounced } from '@vueuse/core';

import { Skeleton } from '@/components/ui/skeleton';
import { formatUsd } from '@/lib/money';

type SizeOption = {
    value: string;
    label: string;
    width: number;
    height: number;
};
type QualityOption = { value: string; label: string; description: string };

const props = defineProps<{
    sizes: SizeOption[];
    qualities: QualityOption[];
    maxPromptLength: number;
    maxReferenceImages: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generate', href: create() }],
    },
});

const form = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [] as string[],
});
const referencePlaceholder = 'https://example.com/image.png';
// Count Unicode characters like the server does, not UTF-16 code units.
const promptLength = computed(() => [...form.prompt].length);
const canAddReference = computed(
    () => form.reference_images.length < props.maxReferenceImages,
);

function addReference(): void {
    if (canAddReference.value) {
        form.reference_images.push('');
    }
}

function removeReference(index: number): void {
    form.reference_images.splice(index, 1);
}

function referenceError(index: number): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `reference_images.${index}`
    ];
}

function submit(): void {
    form.post(store.url(), { preserveScroll: true });
}

type TokenRange = { min: number; max: number };
type Estimate = {
    min_price: number;
    max_price: number;
    markup_percent: number;
    tokens: {
        input_text: TokenRange;
        input_image: number;
        output: number;
        reasoning: TokenRange;
    };
};

const estimate = ref<Estimate | null>(null);
const estimateError = ref<string | null>(null);
const estimating = ref(false);
let pendingEstimate: AbortController | null = null;

// Only filled-in URLs are billed, so empty rows don't count.
const referenceCount = computed(
    () => form.reference_images.filter((url) => url.trim() !== '').length,
);

async function refreshEstimate(): Promise<void> {
    pendingEstimate?.abort(); // a newer request replaces an older one
    const request = new AbortController();
    pendingEstimate = request;
    estimating.value = true;

    try {
        const response = await fetch(
            estimateRoute.url({
                query: {
                    characters: Math.min(
                        promptLength.value,
                        props.maxPromptLength,
                    ),
                    size: form.size,
                    quality: form.quality,
                    reference_images: referenceCount.value,
                },
            }),
            { headers: { Accept: 'application/json' }, signal: request.signal },
        );

        if (!response.ok) {
            throw new Error(`Estimate failed with ${response.status}`);
        }

        estimate.value = (await response.json()) as Estimate;
        estimateError.value = null;
    } catch (error) {
        if (request.signal.aborted) {
            return;
        }
        estimateError.value = 'The estimate is unavailable right now.';
    } finally {
        if (pendingEstimate === request) {
            estimating.value = false;
        }
    }
}

watchDebounced(
    () => [promptLength.value, form.size, form.quality, referenceCount.value],
    () => void refreshEstimate(),
    { debounce: 300, immediate: true },
);

function tokenRange(range: TokenRange): string {
    return range.min === range.max
        ? range.max.toLocaleString()
        : `${range.min.toLocaleString()}–${range.max.toLocaleString()}`;
}
</script>

<template>
    <Head title="Generate" />

    <form
        class="grid gap-6 p-4 lg:grid-cols-[1fr_320px]"
        @submit.prevent="submit"
    >
        <div class="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Prompt</CardTitle>
                    <CardDescription>
                        Describe the image you want.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        rows="6"
                        placeholder="A lighthouse on a cliff at sunset, oil painting"
                        :aria-invalid="!!form.errors.prompt"
                        class="flex min-h-32 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                    />
                    <div class="flex justify-between text-xs">
                        <InputError :message="form.errors.prompt" />
                        <span
                            class="ml-auto text-muted-foreground"
                            :class="{
                                'text-destructive':
                                    promptLength > maxPromptLength,
                            }"
                        >
                            {{ promptLength }} / {{ maxPromptLength }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Size</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-3 gap-3">
                    <button
                        v-for="size in sizes"
                        :key="size.value"
                        type="button"
                        :aria-pressed="form.size === size.value"
                        class="flex flex-col items-center gap-3 rounded-lg border p-4 transition hover:bg-accent"
                        :class="
                            form.size === size.value
                                ? 'border-primary ring-2 ring-primary/20'
                                : 'border-border'
                        "
                        @click="form.size = size.value"
                    >
                        <div class="flex h-16 items-center justify-center">
                            <div
                                class="h-full rounded border-2 border-current bg-muted"
                                :style="{
                                    aspectRatio: `${size.width} / ${size.height}`,
                                }"
                            />
                        </div>
                        <span class="text-sm font-medium">{{
                            size.label
                        }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ size.width }} × {{ size.height }}
                        </span>
                    </button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Quality</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-3 gap-3">
                    <button
                        v-for="quality in qualities"
                        :key="quality.value"
                        type="button"
                        :aria-pressed="form.quality === quality.value"
                        class="flex flex-col gap-1 rounded-lg border p-4 text-left transition hover:bg-accent"
                        :class="
                            form.quality === quality.value
                                ? 'border-primary ring-2 ring-primary/20'
                                : 'border-border'
                        "
                        @click="form.quality = quality.value"
                    >
                        <span class="text-sm font-medium">{{
                            quality.label
                        }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ quality.description }}
                        </span>
                    </button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Reference images</CardTitle>
                    <CardDescription>
                        Optional. Up to {{ maxReferenceImages }} image URLs.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-3">
                    <div
                        v-for="(_, index) in form.reference_images"
                        :key="index"
                        class="grid gap-1"
                    >
                        <Label :for="`reference-${index}`" class="sr-only">
                            Reference image {{ index + 1 }}
                        </Label>
                        <div class="flex gap-2">
                            <Input
                                :id="`reference-${index}`"
                                v-model="form.reference_images[index]"
                                type="url"
                                :placeholder="referencePlaceholder"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Remove reference image ${index + 1}`"
                                @click="removeReference(index)"
                            >
                                <X />
                            </Button>
                        </div>
                        <InputError :message="referenceError(index)" />
                    </div>
                    <InputError :message="form.errors.reference_images" />
                    <Button
                        type="button"
                        variant="outline"
                        class="justify-self-start"
                        :disabled="!canAddReference"
                        @click="addReference"
                    >
                        <ImagePlus /> Add reference image
                    </Button>
                </CardContent>
            </Card>
        </div>

        <aside class="lg:sticky lg:top-4 lg:self-start">
            <Card>
                <CardHeader>
                    <CardTitle>Estimated cost</CardTitle>
                    <CardDescription>
                        The final price is set when the image is ready.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="min-h-9">
                        <p
                            v-if="estimate"
                            class="text-2xl font-semibold tabular-nums transition-opacity"
                            :class="{ 'opacity-50': estimating }"
                        >
                            {{ formatUsd(estimate.min_price) }} –
                            {{ formatUsd(estimate.max_price) }}
                        </p>
                        <p
                            v-else-if="estimateError"
                            class="text-sm text-destructive"
                        >
                            {{ estimateError }}
                        </p>
                        <Skeleton v-else class="h-8 w-48" />
                    </div>

                    <dl
                        v-if="estimate"
                        class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-xs text-muted-foreground"
                    >
                        <dt>Prompt</dt>
                        <dd class="text-right tabular-nums">
                            {{ tokenRange(estimate.tokens.input_text) }} tokens
                        </dd>
                        <dt>Reference images</dt>
                        <dd class="text-right tabular-nums">
                            {{ estimate.tokens.input_image.toLocaleString() }}
                            tokens
                        </dd>
                        <dt>Image output</dt>
                        <dd class="text-right tabular-nums">
                            {{ estimate.tokens.output.toLocaleString() }} tokens
                        </dd>
                        <dt>Reasoning</dt>
                        <dd class="text-right tabular-nums">
                            {{ tokenRange(estimate.tokens.reasoning) }} tokens
                        </dd>
                    </dl>

                    <p v-if="estimate" class="text-xs text-muted-foreground">
                        Includes a {{ estimate.markup_percent }}% service fee.
                        Reasoning varies per image, so you pay the actual cost
                        once it's ready. Failed generations are free.
                    </p>

                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        <Sparkles v-else />
                        Generate
                    </Button>
                </CardContent>
            </Card>
        </aside>
    </form>
</template>
