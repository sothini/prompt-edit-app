const NANOS_PER_DOLLAR = 1_000_000_000;

/**
 * Format integer nanodollars for display. Rounding happens here only, never in stored values.
 */
export function formatUsd(nanos: number, maximumFractionDigits = 4): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits,
    }).format(nanos / NANOS_PER_DOLLAR);
}
