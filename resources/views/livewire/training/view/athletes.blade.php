<div class="space-y-6">
    <x-section title="Athletes & Groups">
        @foreach ($fields as $field)
            <div x-init="
                let pb = $el.querySelector('ui-pillbox');
                if (pb) pb.addEventListener('close', () => $wire.save());
            ">
                <x-cms::form.field :field="$field" />
            </div>
        @endforeach
    </x-section>
</div>
