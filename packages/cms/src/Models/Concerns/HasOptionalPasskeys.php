<?php

namespace Coda\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

if (trait_exists(\Laravel\Passkeys\PasskeyAuthenticatable::class)) {
    trait HasOptionalPasskeys
    {
        use \Laravel\Passkeys\PasskeyAuthenticatable {
            passkeys as protected packagePasskeys;
        }

        // Override the package's relation: it infers the FK from the parent
        // model's class name (e.g. Admin -> admin_id), but the published
        // migration always uses `user_id`.
        public function passkeys(): HasMany
        {
            return $this->hasMany(\Laravel\Passkeys\Passkeys::passkeyModel(), 'user_id');
        }
    }
} else {
    trait HasOptionalPasskeys
    {
    }
}
