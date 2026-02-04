<?php

namespace App\Data\Form\Concerns;

trait HasLiveUpdates
{
    public bool $live = false;

    public function live(bool $live = true): static
    {
        $this->live = $live;

        return $this;
    }
}
