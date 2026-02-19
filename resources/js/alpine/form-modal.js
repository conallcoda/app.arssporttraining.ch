window.focusModalField = function (modalEl, field, index) {
    let target;
    let isTabTarget = false;

    if (index !== null && index !== undefined) {
        target = modalEl.querySelector(`[data-field='${field}'][data-index='${index}']`);
    }
    if (!target) {
        target = modalEl.querySelector(`[data-flux-tab-panel][name='${field}']`);
        if (target) {
            isTabTarget = true;
        }
    }
    if (!target) {
        target = modalEl.querySelector(`[data-field='${field}']:not([data-edit-type])`);
    }
    if (!target) return;

    const panels = [];
    let current = isTabTarget ? target : target.closest('[data-flux-tab-panel]');
    while (current) {
        panels.unshift(current);
        current = current.parentElement
            ? current.parentElement.closest('[data-flux-tab-panel]')
            : null;
    }

    for (const panel of panels) {
        const panelName = panel.getAttribute('name');
        const tabGroup = panel.closest('[data-flux-tab-group]');
        if (!tabGroup || !panelName) continue;
        for (const tab of tabGroup.querySelectorAll(`[data-flux-tab][name='${panelName}']`)) {
            if (tab.closest('[data-flux-tab-group]') === tabGroup) {
                tab.click();
                break;
            }
        }
    }

    if (!isTabTarget) {
        setTimeout(() => {
            const trigger = target.querySelector('[data-flux-pillbox-trigger]');
            if (trigger) {
                trigger.click();
            } else {
                target.focus();
            }
        }, 50);
    }
};

window.handleModalEnterSubmit = function (event, wire, method) {
    if (event.target.tagName === 'TEXTAREA') return;
    if (event.target.closest('[x-data="editable_cell"]')) return;
    if (event.target.closest('[aria-expanded="true"]')) return;
    event.preventDefault();
    wire[method || 'submit']();
};

document.addEventListener('alpine:init', () => {
    Alpine.data('form_modal', (modalName) => ({
        modalName: modalName,

        open(data = {}, title = null, focusField = null, focusIndex = null) {
            Livewire.dispatch('open-' + this.modalName, { data, title, focusField, focusIndex });
        },

        close() {
            $flux.modal(this.modalName).close();
        }
    }));
});
