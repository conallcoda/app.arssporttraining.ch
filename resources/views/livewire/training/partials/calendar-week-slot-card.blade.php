@if ($weekEditMode === 'remove')
    <x-training.calendar-slot-card
        type="button"
        @click.stop
        wire:click="quickRemoveWeekSlot({{ $prog['trainingProgramId'] }}, '{{ $date }}', '{{ $prog['time'] }}')"
        :color="$prog['color']"
        :time="$prog['time']"
        :name="$prog['name']"
        :subtitle="implode(', ', $prog['userNames'])"
    />
@else
    <x-training.calendar-slot-card
        type="button"
        @click.stop
        wire:click="editWeekSlot({{ $prog['trainingProgramId'] }}, '{{ $date }}', '{{ $prog['time'] }}')"
        :color="$prog['color']"
        :time="$prog['time']"
        :name="$prog['name']"
        :subtitle="implode(', ', $prog['userNames'])"
    />
@endif
