document.addEventListener('alpine:init', () => {
    Alpine.data('block_creator_storage', () => ({
        storageKey: 'block-creator-tree',
        hasStoredData: false,
        initialized: false,
        init() {
            const stored = localStorage.getItem(this.storageKey);
            if (stored) {
                try {
                    const treeData = JSON.parse(stored);
                    this.hasStoredData = true;
                    this.$wire.loadFromStorage(treeData).then(() => {
                        this.initialized = true;
                    });
                } catch (e) {
                    console.error('Failed to parse stored tree data:', e);
                    localStorage.removeItem(this.storageKey);
                    this.$wire.initializeWithDefaults().then(() => {
                        this.initialized = true;
                    });
                }
            } else {
                this.$wire.initializeWithDefaults().then(() => {
                    this.initialized = true;
                });
            }
        },
        saveToStorage(block) {
            if (block) {
                localStorage.setItem(this.storageKey, JSON.stringify(block));
                this.hasStoredData = true;
            }
        },
        clearStorage() {
            localStorage.removeItem(this.storageKey);
            this.hasStoredData = false;
            this.$wire.clearStorage();
        }
    }));
});
