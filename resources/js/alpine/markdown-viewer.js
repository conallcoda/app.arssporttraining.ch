document.addEventListener('alpine:init', () => {
    Alpine.data('markdown_viewer', () => ({
        activeSection: '',
        isScrolling: false,
        init() {
            const sections = this.$el.querySelectorAll('section[id]');
            if (sections.length > 0) {
                this.activeSection = sections[0].id;
            }

            if (window.location.hash) {
                const hash = window.location.hash.substring(1);
                this.$nextTick(() => {
                    this.scrollToSection(hash);
                });
            }

            this.$watch('activeSection', (slug) => {
                this.scrollSidebarToActive(slug);
            });
        },
        scrollToSection(slug) {
            const element = document.getElementById(slug);
            if (element) {
                this.isScrolling = true;
                this.activeSection = slug;
                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                window.history.pushState(null, null, '#' + slug);

                setTimeout(() => {
                    this.isScrolling = false;
                }, 1000);
            }
        },
        setActiveSection(slug) {
            if (!this.isScrolling) {
                this.activeSection = slug;
            }
        },
        scrollSidebarToActive(slug) {
            const sidebar = this.$refs.sidebar;
            const activeLink = sidebar?.querySelector(`a[href="#${slug}"]`);

            if (activeLink && sidebar) {
                const sidebarRect = sidebar.getBoundingClientRect();
                const linkRect = activeLink.getBoundingClientRect();
                const linkTop = linkRect.top - sidebarRect.top + sidebar.scrollTop;
                const targetScroll = linkTop - (sidebarRect.height / 3);

                sidebar.scrollTo({
                    top: Math.max(0, targetScroll),
                    behavior: 'smooth'
                });
            }
        },
        destroy() {
        }
    }));
});
