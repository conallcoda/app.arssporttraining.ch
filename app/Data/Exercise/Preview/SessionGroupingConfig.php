<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Fields;

class SessionGroupingConfig extends AbstractData
{
    public function __construct(
        public ?string $mode = null,
        public ?int $groupSize = null,
        public ?bool $copyValuesAutomatically = null,
    ) {
        $this->mode = SessionGroupingMode::normalizeMode($this->mode);
        $this->groupSize = SessionGroupingMode::normalizeGroupSize($this->groupSize, $this->mode);
        $this->copyValuesAutomatically = SessionGroupingMode::normalizeCopyValuesAutomatically($this->copyValuesAutomatically, $this->mode);
    }

    public static function fields(array $data = []): array
    {
        return self::formFields($data);
    }

    public static function formFields(
        array $data = [],
        string $modeField = 'mode',
        string $modeLabel = 'Session Grouping',
    ): array
    {
        $mode = SessionGroupingMode::normalizeMode((string) ($data[$modeField] ?? null));
        $min = SessionGroupingMode::sizeFieldMin($mode);
        $max = SessionGroupingMode::sizeFieldMax($mode);

        return [
            Fields\RadioSegmented::make($modeField)
                ->label($modeLabel)
                ->options(SessionGroupingMode::options())
                ->default(SessionGroupingMode::defaultMode())
                ->rules('required|in:'.implode(',', array_keys(SessionGroupingMode::options())))
                ->live(),
            Fields\Number::make('groupSize')
                ->label(SessionGroupingMode::sizeFieldLabel($mode))
                ->min($min)
                ->max($max)
                ->step(1)
                ->default(SessionGroupingMode::defaultGroupSize($mode))
                ->suffix(SessionGroupingMode::sizeFieldSuffix($mode))
                ->rules($mode === SessionGroupingMode::None->value
                    ? "nullable|integer|min:1|max:{$max}"
                    : "required|integer|min:1|max:{$max}")
                ->show($modeField.' != "none"'),
        ];
    }

    public static function normalizeFormData(array $data, string $modeField = 'mode'): array
    {
        if (($data[$modeField] ?? null) === '') {
            $data[$modeField] = null;
        }

        if (($data['groupSize'] ?? null) === '') {
            $data['groupSize'] = null;
        }

        $mode = SessionGroupingMode::normalizeMode(isset($data[$modeField]) ? (string) $data[$modeField] : null);
        $data[$modeField] = $mode;
        $data['groupSize'] = SessionGroupingMode::normalizeGroupSize(
            isset($data['groupSize']) && is_numeric($data['groupSize']) ? (int) $data['groupSize'] : null,
            $mode,
        );

        if (array_key_exists('copyValuesAutomatically', $data)) {
            $data['copyValuesAutomatically'] = SessionGroupingMode::normalizeCopyValuesAutomatically(
                $data['copyValuesAutomatically'] === '' ? null : (bool) $data['copyValuesAutomatically'],
                $mode,
            );
        }

        return $data;
    }
}
