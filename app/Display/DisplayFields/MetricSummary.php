<?php

namespace App\Display\DisplayFields;

use App\Data\Athlete\Metric\AbstractMetric;
use Coda\Cms\Display\Concerns\HasPrefix;
use Coda\Cms\Display\Concerns\HasSuffix;
use Coda\Cms\Display\DisplayField;

class MetricSummary extends DisplayField
{
    use HasPrefix;
    use HasSuffix;

    public string $type = 'metric-summary';

    public function formatValue(mixed $value): string
    {
        if ($value instanceof AbstractMetric) {
            return $value->summary();
        }

        if (is_array($value) && ! empty($value) && isset($value[0]['label'])) {
            return implode("\n", array_map(
                fn (array $item) => "{$item['label']}: {$item['summary']}",
                $value
            ));
        }

        if (is_array($value)) {
            return implode("\n", array_map(fn (AbstractMetric $m) => $m->summary(), $value));
        }

        return '';
    }
}
