<?php

namespace Coda\Cms\Models\Contracts;

if (interface_exists(\Laravel\Passkeys\Contracts\PasskeyUser::class)) {
    interface OptionalPasskeyUser extends \Laravel\Passkeys\Contracts\PasskeyUser
    {
    }
} else {
    interface OptionalPasskeyUser
    {
    }
}
