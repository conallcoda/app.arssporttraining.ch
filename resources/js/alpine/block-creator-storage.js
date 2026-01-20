document.addEventListener('alpine:init', () => {
    Alpine.data('block_creator_storage', (config = {}) => ({
        storageKey: 'block-creator-data',
        persistToStorage: config.persistToStorage ?? false,
        hasStoredData: false,
        initialized: false,

        init() {
            if (!this.persistToStorage) {
                this.$wire.initializeWithDefaults().then(() => {
                    this.initialized = true;
                });
                return;
            }

            const stored = localStorage.getItem(this.storageKey);
            if (stored) {
                try {
                    const data = JSON.parse(stored);
                    if (data.tree) {
                        this.hasStoredData = true;
                        this.$wire.loadFromStorage(data.tree).then(() => {
                            this.initialized = true;
                        });
                    } else {
                        this.$wire.initializeWithDefaults().then(() => {
                            this.initialized = true;
                        });
                    }
                } catch (e) {
                    console.error('Failed to parse stored data:', e);
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

        saveToStorage(detail) {
            if (!this.persistToStorage) return;

            if (detail && detail.block) {
                const data = {
                    tree: detail.block
                };
                localStorage.setItem(this.storageKey, JSON.stringify(data));
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
