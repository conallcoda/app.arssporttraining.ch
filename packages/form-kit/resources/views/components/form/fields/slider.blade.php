<div {{ $attributes }}>
    <x-form-kit::form.slider-with-input :label="$field->getLabel()" :model="$wireModel" :min="$field->min" :max="$field->max"
        :step="$field->step" :suffix="$resolvedSuffix" :ticks="$field->ticks" :required="$field->required" />
</div>
