<div>
    <div x-data="event_calendar" data-events='@json($this->events)' data-view="{{ $currentView }}">
        <div class="flex items-center justify-end mb-4 gap-1">
            <button @click="setView('dayGridMonth')"
                x-bind:class="currentView === 'dayGridMonth'
                    ?
                    'bg-zinc-800 text-white dark:bg-white dark:text-zinc-800' :
                    'bg-transparent text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10'"
                class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-sm font-medium transition">Month</button>
            <button @click="setView('dayGridWeek')"
                x-bind:class="currentView === 'dayGridWeek'
                    ?
                    'bg-zinc-800 text-white dark:bg-white dark:text-zinc-800' :
                    'bg-transparent text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10'"
                class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-sm font-medium transition">Week</button>
            <button @click="setView('dayGridDay')"
                x-bind:class="currentView === 'dayGridDay'
                    ?
                    'bg-zinc-800 text-white dark:bg-white dark:text-zinc-800' :
                    'bg-transparent text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10'"
                class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-sm font-medium transition">Day</button>
        </div>

        <div data-calendar-container></div>

        <template x-ref="slotTable">
            <div class="flex flex-col flex-1 w-full text-xs bg-blue-500/10">
                <div data-slot="am" class="flex flex-1 border-b border-t border-zinc-200/60 dark:border-white/5">
                    <div
                        class="text-xs font-semibold uppercase text-zinc-400 w-8 shrink-0 flex items-center justify-center border-r border-zinc-200/60 dark:border-white/5">
                        AM</div>
                    <div class="p-2 flex-1" data-programs></div>
                </div>
                <div data-slot="pm" class="flex flex-1">
                    <div
                        class="text-xs font-semibold uppercase text-zinc-400 w-8 shrink-0 flex items-center justify-center border-r border-zinc-200/60 dark:border-white/5">
                        PM</div>
                    <div class="p-2 flex-1" data-programs></div>
                </div>
            </div>
        </template>

        <template x-ref="programBlock">
            <div class="rounded px-1.5 py-0.5 mb-2 text-white text-[11px] leading-snug font-medium truncate"></div>
        </template>
    </div>

    <style>
        .ec {
            --ec-bg-color: transparent;
            --ec-border-color: oklch(0.871 0 0);
            --ec-button-bg-color: transparent;
            --ec-button-border-color: oklch(0.871 0 0);
            --ec-button-text-color: currentcolor;
            --ec-button-active-bg-color: oklch(0.967 0 0);
            --ec-button-active-border-color: oklch(0.708 0 0);
            --ec-today-bg-color: oklch(0.97 0.014 254.604);
        }

        .dark .ec {
            --ec-border-color: rgba(255, 255, 255, 0.1);
            --ec-button-border-color: rgba(255, 255, 255, 0.15);
            --ec-button-active-bg-color: rgba(255, 255, 255, 0.1);
            --ec-button-active-border-color: rgba(255, 255, 255, 0.25);
            --ec-today-bg-color: rgba(59, 130, 246, 0.08);
            --ec-color-300: rgba(255, 255, 255, 0.1);
            --ec-color-200: rgba(255, 255, 255, 0.05);
        }

        .ec-day-grid .ec-day {
            display: flex;
            flex-direction: column;
        }

        .ec-day-grid .ec-day-head {
            position: relative;
            z-index: 10;
            flex-shrink: 0;
        }

        .ec-day-grid .ec-events {
            display: contents;
        }

        .ec-day-grid .ec-body {

            overflow: visible !important;
        }

        .ec-day-grid .ec-day .ec-day-head+.ec-events {
            order: 1;
        }

        .ec-day-grid .ec-day .ec-bg-events {
            display: none;
        }

        .ec-day-grid .ec-day-foot {
            display: none;
        }

        .ec .ec-event.ec-day-slots {
            background-color: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 1.75rem 0 0 0 !important;
            margin: 0 !important;
            margin-block-start: 0 !important;
            color: inherit !important;
            position: relative !important;
            width: 100% !important;
            inline-size: 100% !important;
            align-self: stretch !important;
            display: flex !important;
            flex-direction: column;
        }

        .ec .ec-event.ec-day-slots .ec-event-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
    </style>
</div>
