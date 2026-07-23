import { buildBarChartConfig } from "../charts/types/bar.js";
import { buildPieChartConfig } from "../charts/types/pie.js";

const chartConfigBuilders = {
    bar: buildBarChartConfig,
    pie: buildPieChartConfig,
};

export function registerCmsChart(Alpine, dependencies) {
    const {
        Chart,
        PieController,
        ArcElement,
        BarController,
        BarElement,
        CategoryScale,
        LinearScale,
        Tooltip,
        Legend,
        ChartDataLabels,
    } = dependencies;

    Chart.register(
        PieController,
        ArcElement,
        BarController,
        BarElement,
        CategoryScale,
        LinearScale,
        Tooltip,
        Legend,
        ChartDataLabels,
    );

    Alpine.data("cmsChart", (definition = null) => ({
        definition,
        instance: null,

        init() {
            this.renderChart();
        },

        setDefinition(definition) {
            this.definition = definition;
            this.renderChart();
        },

        renderChart() {
            const builder = chartConfigBuilders[this.definition?.type];

            if (!builder || !this.$refs.canvas) {
                if (this.instance) {
                    this.instance.destroy();
                    this.instance = null;
                }

                return;
            }

            if (this.instance) {
                this.instance.destroy();
            }

            this.instance = new Chart(
                this.$refs.canvas.getContext("2d"),
                builder(this.definition),
            );
        },
    }));
}
