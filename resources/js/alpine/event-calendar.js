import { createCalendar, destroyCalendar, DayGrid } from '@event-calendar/core';

document.addEventListener('alpine:init', () => {
    Alpine.data('event_calendar', () => ({
        calendar: null,
        currentView: 'dayGridMonth',

        init() {
            const events = JSON.parse(this.$el.dataset.events || '[]');
            const initialView = this.$el.dataset.view || 'dayGridMonth';
            this.currentView = initialView;

            const container = this.$el.querySelector('[data-calendar-container]');

            this.calendar = createCalendar(container, [DayGrid], {
                view: initialView,
                events: events,
                headerToolbar: {
                    start: 'prev,next today',
                    center: 'title',
                    end: '',
                },
                dayMaxEvents: false,
                displayEventEnd: false,
                eventContent: (info) => this.renderDayEvent(info),
                eventClassNames: () => ['ec-day-slots'],
                eventClick: (info) => this.handleEventClick(info),
            });

            if (this.$wire) {
                this.$wire.$watch('events', (newEvents) => {
                    if (this.calendar) {
                        this.calendar.setOption('events', newEvents);
                    }
                });
            }
        },

        setView(view) {
            this.currentView = view;
            if (this.calendar) {
                this.calendar.setOption('view', view);
            }
        },

        renderDayEvent(info) {
            const slots = info.event.extendedProps?.slots || { am: [], pm: [] };
            const table = this.$refs.slotTable.content.cloneNode(true).firstElementChild;

            ['am', 'pm'].forEach((key) => {
                const row = table.querySelector(`[data-slot="${key}"]`);
                const cell = row.querySelector('[data-programs]');
                const programs = slots[key] || [];

                if (!programs.length) {
                    row.classList.add('opacity-30');
                    return;
                }

                programs.forEach((p) => {
                    const block = this.$refs.programBlock.content.cloneNode(true).firstElementChild;
                    block.textContent = p.name;
                    block.style.backgroundColor = p.color;
                    cell.appendChild(block);
                });
            });

            return { domNodes: [table] };
        },

        handleEventClick(info) {
            if (this.$wire) {
                this.$wire.call('onEventClick', String(info.event.id));
            }
        },

        destroy() {
            if (this.calendar) {
                destroyCalendar(this.calendar);
                this.calendar = null;
            }
        },
    }));
});
