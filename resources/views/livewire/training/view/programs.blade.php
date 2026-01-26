<div class="space-y-6">
    <x-section title="Programs">
        <livewire:training.view.training-program-list :training-plan="$trainingPlan" />
    </x-section>

    @if (count($this->programs) > 0)
        <x-section title="Overrides">
            <flux:tab.group>
                <flux:tabs>
                    @foreach ($this->programs as $program)
                        <flux:tab name="program-{{ $program->id }}" wire:key="tab-{{ $program->id }}">
                            {{ $program->name }}
                        </flux:tab>
                    @endforeach
                </flux:tabs>

                @foreach ($this->programs as $program)
                    <flux:tab.panel name="program-{{ $program->id }}" wire:key="panel-{{ $program->id }}">
                        <livewire:training.view.program-exercise-override-list
                            :program="$program"
                            wire:key="override-list-{{ $program->id }}"
                        />
                    </flux:tab.panel>
                @endforeach
            </flux:tab.group>
        </x-section>
    @endif
</div>
