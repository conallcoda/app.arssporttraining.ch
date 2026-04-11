<div>
    <x-training.calendar-slot-card
        type="button"
        @click.stop="$wire.editWeekSlot(prog.trainingProgramId, '{{ $day['date'] }}', prog.time)"
        class="w-full"
        color-expr="prog.color"
        time-expr="prog.time"
        name-expr="prog.name"
        subtitle-expr="(prog.userNames || []).join(', ')"
    />
</div>
