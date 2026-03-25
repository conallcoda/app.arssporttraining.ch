<div>
    <button type="button" @click.stop="$wire.editWeekSlot(prog.trainingProgramId, '{{ $day['date'] }}', prog.time)"
        class="flex flex-col px-2 py-1.5 rounded-lg text-left cursor-pointer hover:opacity-80 transition-opacity w-full"
        :class="prog.color ? '' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400'"
        :style="prog.color ? 'background-color: var(--color-' + prog.color + '-500); color: white;' : ''">
        <span class="text-[10px]" :class="prog.color ? 'opacity-80' : 'opacity-60'" x-text="prog.time"></span>
        <span class="text-xs font-medium truncate" x-text="prog.name"></span>
        <span class="text-[10px] truncate" :class="prog.color ? 'opacity-80' : 'opacity-60'" x-text="(prog.userNames || []).join(', ')"></span>
    </button>
</div>
