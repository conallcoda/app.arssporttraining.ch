document.addEventListener('alpine:init', () => {
    window.Alpine.data('cmsPasskeys', ({ csrfToken } = {}) => ({
        isSupported: false,
        isVerifying: false,
        isRegistering: false,
        error: null,

        async init() {
            try {
                const { Passkeys } = await loadPasskeys();
                this.isSupported = Passkeys.isSupported();
            } catch (e) {
                this.isSupported = false;
                this.error = passkeyErrorMessage(e);
            }
        },

        async verify() {
            this.error = null;
            this.isVerifying = true;
            try {
                const { Passkeys } = await loadPasskeys();
                const response = await Passkeys.verify();
                window.location.href = response?.redirect || '/';
            } catch (e) {
                this.error = passkeyErrorMessage(e);
            } finally {
                this.isVerifying = false;
            }
        },

        async register(name) {
            if (!name) return;
            this.error = null;
            this.isRegistering = true;
            try {
                const { Passkeys } = await loadPasskeys();
                await Passkeys.register({ name });
                window.dispatchEvent(new CustomEvent('passkey-registered'));
            } catch (e) {
                this.error = passkeyErrorMessage(e);
            } finally {
                this.isRegistering = false;
            }
        },
    }));
});

let passkeysModule = null;

function loadPasskeys() {
    if (!passkeysModule) {
        passkeysModule = import('@laravel/passkeys');
    }
    return passkeysModule;
}

function passkeyErrorMessage(error) {
    if (!error) return null;
    if (error.name === 'NotSupportedError') return 'This browser does not support passkeys.';
    if (error.name === 'UserCancelledError') return null;
    if (error.name === 'PasskeyExistsError') return 'A passkey for this account is already registered on this device.';
    return error.message || 'Passkey operation failed.';
}
