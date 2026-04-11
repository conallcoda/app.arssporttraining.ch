const KEYBOARD_OPEN_THRESHOLD = 120;

let teardownAthleteMobileShell = null;

function mountAthleteMobileShell() {
    teardownAthleteMobileShell?.();

    const shell = document.querySelector('[data-athlete-shell]');
    const nav = document.querySelector('[data-athlete-bottom-nav]');

    if (!shell || !nav) {
        teardownAthleteMobileShell = null;
        return;
    }

    const sync = () => {
        window.requestAnimationFrame(() => {
            const visualViewport = window.visualViewport;

            if (visualViewport) {
                const obscuredHeight = Math.max(0, window.innerHeight - visualViewport.height - visualViewport.offsetTop);
                const keyboardOpen = obscuredHeight > KEYBOARD_OPEN_THRESHOLD;

                shell.classList.toggle('athlete-shell--keyboard-open', keyboardOpen);

                if (keyboardOpen) {
                    shell.style.setProperty('--athlete-bottom-nav-height', '0px');
                    return;
                }
            } else {
                shell.classList.remove('athlete-shell--keyboard-open');
            }

            shell.style.setProperty('--athlete-bottom-nav-height', `${Math.ceil(nav.getBoundingClientRect().height)}px`);
        });
    };

    const resizeObserver = typeof ResizeObserver === 'function'
        ? new ResizeObserver(sync)
        : null;

    resizeObserver?.observe(nav);

    const visualViewport = window.visualViewport;

    window.addEventListener('resize', sync);
    window.addEventListener('orientationchange', sync);
    visualViewport?.addEventListener('resize', sync);
    visualViewport?.addEventListener('scroll', sync);
    document.addEventListener('focusin', sync);
    document.addEventListener('focusout', sync);

    sync();

    teardownAthleteMobileShell = () => {
        resizeObserver?.disconnect();
        window.removeEventListener('resize', sync);
        window.removeEventListener('orientationchange', sync);
        visualViewport?.removeEventListener('resize', sync);
        visualViewport?.removeEventListener('scroll', sync);
        document.removeEventListener('focusin', sync);
        document.removeEventListener('focusout', sync);
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAthleteMobileShell, { once: true });
} else {
    mountAthleteMobileShell();
}

document.addEventListener('livewire:navigated', mountAthleteMobileShell);
