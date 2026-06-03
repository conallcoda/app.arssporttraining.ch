document.addEventListener('alpine:init', () => {
    window.Alpine.data('cmsPasskeys', ({ csrfToken, routes = {}, realm = null, userId = null } = {}) => ({
        realm,
        userId: userId ? String(userId) : null,
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
                const response = await Passkeys.verify({ routes: routes.verify || {} });
                rememberDevicePasskey(response?.realm || this.realm, response?.user?.id || this.userId);
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
                await Passkeys.register({ name, routes: routes.register || {} });
                rememberDevicePasskey(this.realm, this.userId);
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
const DEVICE_PASSKEY_STORAGE_PREFIX = 'auth-kit.passkey-device';
const PASSKEY_PROMPT_DISMISSED_STORAGE_PREFIX = 'auth-kit.passkey-prompt-dismissed';

window.authKitHasPasskeyOnDevice = hasDevicePasskey;
window.authKitRememberPasskeyOnDevice = rememberDevicePasskey;
window.authKitForgetPasskeyOnDevice = forgetDevicePasskey;
window.authKitDismissPasskeyPromptOnDevice = dismissPasskeyPromptOnDevice;
window.authKitHasDismissedPasskeyPromptOnDevice = hasDismissedPasskeyPromptOnDevice;
window.authKitClearPasskeyPromptDismissal = clearPasskeyPromptDismissal;

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

function hasDevicePasskey(realm, userId) {
    const key = devicePasskeyStorageKey(realm, userId);

    if (!key) {
        return false;
    }

    return window.localStorage.getItem(key) === '1';
}

function rememberDevicePasskey(realm, userId) {
    const key = devicePasskeyStorageKey(realm, userId);

    if (!key) {
        return;
    }

    window.localStorage.setItem(key, '1');
}

function forgetDevicePasskey(realm, userId) {
    const key = devicePasskeyStorageKey(realm, userId);

    if (!key) {
        return;
    }

    window.localStorage.removeItem(key);
}

function dismissPasskeyPromptOnDevice(realm, userId) {
    const key = passkeyPromptDismissedStorageKey(realm, userId);

    if (!key) {
        return;
    }

    window.localStorage.setItem(key, String(Date.now()));
}

function hasDismissedPasskeyPromptOnDevice(realm, userId) {
    const key = passkeyPromptDismissedStorageKey(realm, userId);

    if (!key) {
        return false;
    }

    return window.localStorage.getItem(key) !== null;
}

function clearPasskeyPromptDismissal(realm, userId) {
    const key = passkeyPromptDismissedStorageKey(realm, userId);

    if (!key) {
        return;
    }

    window.localStorage.removeItem(key);
}

function devicePasskeyStorageKey(realm, userId) {
    if (!realm || !userId) {
        return null;
    }

    return `${DEVICE_PASSKEY_STORAGE_PREFIX}.${realm}.${userId}`;
}

function passkeyPromptDismissedStorageKey(realm, userId) {
    if (!realm || !userId) {
        return null;
    }

    return `${PASSKEY_PROMPT_DISMISSED_STORAGE_PREFIX}.${realm}.${userId}`;
}
