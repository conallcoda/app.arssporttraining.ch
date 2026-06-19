/**
 * @typedef {{
 *   label: string,
 *   values: Array<number>,
 *   backgroundColor: Array<string>,
 *   borderColor: Array<string>,
 *   borderWidth: number
 * }} ChartDataset
 *
 * @typedef {{
 *   id: string,
 *   type: 'bar',
 *   title: string,
 *   description: string,
 *   data: {
 *     labels: Array<string>,
 *     datasets: Array<ChartDataset>,
 *   },
 *   options?: {
 *     legendPosition?: 'top' | 'left' | 'bottom' | 'right' | 'chartArea',
 *     valueSuffix?: string,
 *     valueDecimals?: number,
 *     suggestedMax?: number | null,
 *     indexAxis?: 'x' | 'y',
 *     stacked?: boolean,
 *     barBorderRadius?: number,
 *     barBorderWidth?: number,
 *     xTickMinRotation?: number,
 *     xTickMaxRotation?: number,
 *     yTickStepSize?: number | null,
 *     legendSwatches?: Array<{ fillStyle?: string, strokeStyle?: string }>,
 *     tooltipTitles?: Array<string>,
 *     tooltipMetrics?: Array<{
 *       title: string,
 *       supplyCount: number,
 *       demandCount: number,
 *       supplyPercent: number,
 *       demandPercent: number,
 *     }>,
 *   },
 * }} BarChartDefinition
 */

/**
 * @param {BarChartDefinition} definition
 * @returns {import('chart.js').ChartConfiguration<'bar'>}
 */
export function buildBarChartConfig(definition) {
    const valueSuffix = definition.options?.valueSuffix ?? "";
    const valueDecimals = definition.options?.valueDecimals ?? 1;

    return {
        type: "bar",
        data: {
            labels: definition.data.labels,
            datasets: definition.data.datasets.map((dataset) => ({
                label: dataset.label,
                data: dataset.values,
                backgroundColor: dataset.backgroundColor,
                borderColor: dataset.borderColor,
                borderWidth: definition.options?.barBorderWidth ?? dataset.borderWidth,
                borderRadius: definition.options?.barBorderRadius ?? 6,
                borderSkipped: false,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: {
                mode: "index",
                intersect: false,
            },
            scales: {
                x: {
                    stacked: definition.options?.stacked ?? false,
                    ticks: {
                        color: "#a1a1aa",
                        minRotation: definition.options?.xTickMinRotation ?? 0,
                        maxRotation: definition.options?.xTickMaxRotation ?? 0,
                    },
                    grid: {
                        display: false,
                    },
                },
                y: {
                    beginAtZero: true,
                    stacked: definition.options?.stacked ?? false,
                    suggestedMax: definition.options?.suggestedMax ?? undefined,
                    ticks: {
                        color: "#a1a1aa",
                        stepSize: definition.options?.yTickStepSize ?? undefined,
                        callback(value) {
                            return `${value}${valueSuffix}`;
                        },
                    },
                    grid: {
                        color: "rgba(161, 161, 170, 0.15)",
                    },
                },
            },
            indexAxis: definition.options?.indexAxis ?? "x",
            plugins: {
                legend: {
                    position: definition.options?.legendPosition ?? "bottom",
                },
                datalabels: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        title(contexts) {
                            const dataIndex = contexts[0]?.dataIndex ?? 0;

                            return definition.options?.tooltipTitles?.[dataIndex]
                                ?? contexts[0]?.label
                                ?? "";
                        },
                        label(context) {
                            const label = context.dataset?.label ?? "";
                            const dataIndex = context.dataIndex ?? 0;
                            const metric = definition.options?.tooltipMetrics?.[dataIndex];

                            if (metric) {
                                if (label === "Supply") {
                                    return `Supply: ${metric.supplyCount} (${Number(metric.supplyPercent).toFixed(1)}%)`;
                                }

                                if (label === "Demand") {
                                    return `Demand: ${metric.demandCount} (${Number(metric.demandPercent).toFixed(1)}%)`;
                                }
                            }

                            const value = Number(context.raw ?? 0).toFixed(valueDecimals);

                            return `${label}: ${value}${valueSuffix}`;
                        },
                    },
                },
            },
        },
    };
}
