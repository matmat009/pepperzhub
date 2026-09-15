<script setup lang="ts">
import { VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';
import {
    ChartContainer,
    ChartCrosshair,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { formatDay, formatFullDay } from '../types';
import type { SalesPoint } from '../types';

/**
 * The daily revenue trend.
 *
 * Built on the shadcn-vue chart primitives already vendored in
 * components/ui/chart (Unovis underneath) rather than a second charting
 * library — they carry the theme tokens, the tooltip shell and the dark-mode
 * handling this page would otherwise reimplement.
 */
const props = defineProps<{
    points: SalesPoint[];
}>();

const config = {
    revenue: { label: 'Revenue', color: 'var(--color-sf-primary)' },
} satisfies ChartConfig;

// Unovis addresses points by index; the date is looked back up for the labels.
const x = (_point: SalesPoint, index: number) => index;
const y = (point: SalesPoint) => point.revenue;

/**
 * Roughly eight ticks however long the range is, so a month reads every few
 * days and a year does not stack a label per day into a grey smear.
 */
const tickStep = computed(() =>
    Math.max(1, Math.ceil(props.points.length / 8)),
);

const tickFormat = (value: number) => {
    const point = props.points[Math.round(value)];

    if (!point || Math.round(value) % tickStep.value !== 0) {
        return '';
    }

    return formatDay(point.date);
};

const tooltip = computed(() =>
    componentToString(config, ChartTooltipContent, {
        config,
        indicator: 'line' as const,
        labelFormatter: (value: number | Date) =>
            formatFullDay(props.points[Math.round(Number(value))]?.date ?? ''),
    }),
);

// The y-axis is money; the tooltip's own number is formatted by the shared
// helper so the chart and the figure above it read in the same currency.
const yFormat = (value: number) => formatPrice(value);

const empty = computed(() =>
    props.points.every((point) => point.revenue === 0),
);
</script>

<template>
    <div class="relative">
        <ChartContainer :config="config" cursor class="aspect-[3/1] min-h-64">
            <VisXYContainer
                :data="points"
                :margin="{ top: 8, right: 8, bottom: 4, left: 8 }"
            >
                <VisLine
                    :x="x"
                    :y="y"
                    color="var(--color-sf-primary)"
                    :line-width="2"
                    curve-type="monotoneX"
                />
                <VisAxis
                    type="x"
                    :tick-format="tickFormat"
                    :grid-line="false"
                    :tick-line="false"
                    :domain-line="false"
                />
                <VisAxis
                    type="y"
                    :tick-format="yFormat"
                    :tick-line="false"
                    :domain-line="false"
                />
                <ChartCrosshair
                    color="var(--color-sf-primary)"
                    :template="tooltip"
                />
                <ChartTooltip />
            </VisXYContainer>
        </ChartContainer>

        <!--
            Sits over the chart rather than replacing it: the axes still show
            the range that was asked for, which is the context that makes "no
            sales" mean something.
        -->
        <p
            v-if="empty"
            class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-muted-foreground"
        >
            No verified sales in this range.
        </p>
    </div>
</template>
