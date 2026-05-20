<div class="sr-only" aria-hidden="true">
    @foreach ($this->fieldsets as $fieldset)
        @foreach ($fieldset->fields as $field)
            <x-form-kit::form.field :field="$field" :prefix="$fieldset->prefix ?? 'data'" />
        @endforeach
    @endforeach
</div>
