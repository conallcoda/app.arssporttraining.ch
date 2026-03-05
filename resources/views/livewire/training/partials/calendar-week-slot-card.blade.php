@if ($weekEditMode === 'remove')
    @if ($prog['color'])
        <button type="button" @click.stop wire:click="quickRemoveWeekSlot({{ $prog['trainingProgramId'] }}, '{{ $date }}', '{{ $prog['time'] }}')"
            class="flex flex-col px-2 py-1.5 rounded-lg text-left cursor-pointer hover:opacity-80 transition-opacity"
            style="{{ \Coda\Cms\Support\ColorPalette::solid($prog['color']) }}">
            <span class="text-[10px] opacity-80">{{ $prog['time'] }}</span>
            <span class="text-xs font-medium truncate">{{ $prog['name'] }}</span>
            <span class="text-[10px] opacity-80 truncate">{{ implode(', ', $prog['userNames']) }}</span>
        </button>
    @else
        <button type="button" @click.stop wire:click="quickRemoveWeekSlot({{ $prog['trainingProgramId'] }}, '{{ $date }}', '{{ $prog['time'] }}')"
            class="flex flex-col px-2 py-1.5 rounded-lg text-left cursor-pointer hover:opacity-80 transition-opacity bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
            <span class="text-[10px] opacity-60">{{ $prog['time'] }}</span>
            <span class="text-xs font-medium truncate">{{ $prog['name'] }}</span>
            <span class="text-[10px] opacity-60 truncate">{{ implode(', ', $prog['userNames']) }}</span>
        </button>
    @endif
@else
    @if ($prog['color'])
        <button type="button" @click.stop wire:click="editWeekSlot({{ $prog['trainingProgramId'] }}, '{{ $date }}', '{{ $prog['time'] }}')"
            class="flex flex-col px-2 py-1.5 rounded-lg text-left cursor-pointer hover:opacity-80 transition-opacity"
            style="{{ \Coda\Cms\Support\ColorPalette::solid($prog['color']) }}">
            <span class="text-[10px] opacity-80">{{ $prog['time'] }}</span>
            <span class="text-xs font-medium truncate">{{ $prog['name'] }}</span>
            <span class="text-[10px] opacity-80 truncate">{{ implode(', ', $prog['userNames']) }}</span>
        </button>
    @else
        <button type="button" @click.stop wire:click="editWeekSlot({{ $prog['trainingProgramId'] }}, '{{ $date }}', '{{ $prog['time'] }}')"
            class="flex flex-col px-2 py-1.5 rounded-lg text-left cursor-pointer hover:opacity-80 transition-opacity bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
            <span class="text-[10px] opacity-60">{{ $prog['time'] }}</span>
            <span class="text-xs font-medium truncate">{{ $prog['name'] }}</span>
            <span class="text-[10px] opacity-60 truncate">{{ implode(', ', $prog['userNames']) }}</span>
        </button>
    @endif
@endif
