<?php

namespace Coda\Cms\Models\Concerns;

use Coda\AuthKit\Relations\TypedPasskeyRelation;
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
            if (class_exists(\Coda\AuthKit\Models\AuthPasskey::class)
                && is_a(\Laravel\Passkeys\Passkeys::passkeyModel(), \Coda\AuthKit\Models\AuthPasskey::class, true)) {
                $instance = $this->newRelatedInstance(\Laravel\Passkeys\Passkeys::passkeyModel());

                return new TypedPasskeyRelation(
                    $instance->newQuery(),
                    $this,
                    'user_id',
                    $this->getKeyName(),
                    'user_type',
                    $this->getMorphClass(),
                );
            }

            return $this->hasMany(\Laravel\Passkeys\Passkeys::passkeyModel(), 'user_id');
        }
    }
} else {
    trait HasOptionalPasskeys
    {
    }
}
