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
 *   type: 'pie',
 *   title: string,
 *   description: string,
 *   data: {
 *     labels: Array<string>,
 *     datasets: Array<ChartDataset>,
 *   },
 *   options?: {
 *     legendPosition?: 'top' | 'left' | 'bottom' | 'right' | 'chartArea',
 *     legendReverse?: boolean,
 *     showSliceLabels?: boolean,
 *     sliceLabelFormat?: 'percentage' | 'value',
 *     rotationDegrees?: number,
 *   },
 * }} PieChartDefinition
 */

function slicePercentage(dataset, value) {
    const total = dataset.reduce(
        (sum, entry) => sum + Number(entry ?? 0),
        0,
    );

    return total > 0 ? ((Number(value ?? 0) / total) * 100) : 0;
}

/**
 * @param {PieChartDefinition} definition
 * @returns {import('chart.js').ChartConfiguration<'pie'>}
 */
export function buildPieChartConfig(definition) {
    return {
        type: "pie",
        data: {
            labels: definition.data.labels,
            datasets: definition.data.datasets.map((dataset) => ({
                label: dataset.label,
                data: dataset.values,
                backgroundColor: dataset.backgroundColor,
                borderColor: dataset.borderColor,
                borderWidth: dataset.borderWidth,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            rotation: ((definition.options?.rotationDegrees ?? -90) * Math.PI) / 180,
            plugins: {
                legend: {
                    position: definition.options?.legendPosition ?? "bottom",
                    reverse: definition.options?.legendReverse ?? false,
                },
                datalabels: {
                    display: definition.options?.showSliceLabels ?? false,
                    color: "#111827",
                    font(context) {
                        const value = Number(context.dataset?.data?.[context.dataIndex] ?? 0);
                        const percentage = slicePercentage(context.dataset?.data ?? [], value);
                        const isSmallSlice = percentage < 10;

                        return {
                            weight: "600",
                            size: isSmallSlice ? 13 : 16,
                        };
                    },
                    anchor(context) {
                        const value = Number(context.dataset?.data?.[context.dataIndex] ?? 0);
                        const percentage = slicePercentage(context.dataset?.data ?? [], value);

                        return percentage < 10 ? "end" : "center";
                    },
                    align(context) {
                        const value = Number(context.dataset?.data?.[context.dataIndex] ?? 0);
                        const percentage = slicePercentage(context.dataset?.data ?? [], value);

                        return percentage < 10 ? "start" : "center";
                    },
                    offset(context) {
                        const value = Number(context.dataset?.data?.[context.dataIndex] ?? 0);
                        const percentage = slicePercentage(context.dataset?.data ?? [], value);

                        return percentage < 10 ? 8 : 0;
                    },
                    clamp: true,
                    clip: false,
                    formatter(value, context) {
                        const format = definition.options?.sliceLabelFormat ?? "percentage";

                        if (format === "value") {
                            return `${value}`;
                        }

                        return `${slicePercentage(context.dataset?.data ?? [], value).toFixed(1)}%`;
                    },
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const label = context.label ?? "";
                            const value = Number(context.raw ?? 0);
                            const percentage = slicePercentage(
                                context.dataset?.data ?? [],
                                value,
                            ).toFixed(1);

                            return `${label}: ${value} (${percentage}%)`;
                        },
                    },
                },
            },
        },
    };
}
