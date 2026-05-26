@php
    $rawValue = data_get($this, $wireModel);
    $initialValue = is_array($rawValue)
        ? $rawValue
        : (is_array($field->default) ? $field->default : ['blocks' => []]);
@endphp

@once
    <style>
        .document-writer-editor .codex-editor__redactor {
            padding-bottom: 0 !important;
        }

        .document-writer-editor .ce-block__content,
        .document-writer-editor .ce-toolbar__content,
        .document-writer-editor .ce-inline-toolbar,
        .document-writer-editor .ce-conversion-toolbar {
            max-width: none;
        }

        .document-writer-editor .ce-block__content,
        .document-writer-editor .ce-toolbar__content {
            margin-left: 0;
            margin-right: 0;
        }

        .document-writer-editor .ce-header,
        .document-writer-editor .ce-paragraph,
        .document-writer-editor .cdx-list,
        .document-writer-editor .cdx-quote,
        .document-writer-editor .tc-wrap {
            padding-left: 0;
            padding-right: 0;
        }
    </style>
@endonce

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    <div
        x-data="document_writer({
            wireModel: @js($wireModel),
            value: @js($initialValue),
            placeholder: @js($explicitPlaceholder($field) ?? 'Type / to insert a block'),
            minHeight: {{ $field->minHeight }},
            autofocus: @js($field->autofocus),
            chartTypes: @js($field->chartTypes),
        })"
        x-on:form-kit:before-submit.window="flushSync()"
        wire:key="document-writer-{{ $wireModel }}"
    >
        <div class="rounded-2xl border border-zinc-300/80 bg-zinc-800/5 px-2 py-2 shadow-sm transition focus-within:border-zinc-400 dark:border-zinc-700 dark:bg-white/10 dark:focus-within:border-zinc-500">
            <div
                x-ref="holder"
                wire:ignore
                class="document-writer-editor"
                style="min-height: {{ $field->minHeight }}px;"
            ></div>
        </div>
    </div>
</x-form-kit::form.field-shell>
