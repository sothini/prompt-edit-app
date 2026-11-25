<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ImagePlus, Sparkles, X } from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
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
                    <p class="text-3xl font-semibold tabular-nums">—</p>
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
