<?php

namespace Coda\FormKit\Concerns;

trait HasLiveUpdates
{
    public bool $live = false;

    public bool $blur = false;

    public ?int $debounce = null;

    public function live(bool $live = true): static
    {
        $this->live = $live;

        return $this;
    }

    public function blur(bool $blur = true): static
    {
        $this->blur = $blur;

        return $this;
    }

    public function debounce(int $milliseconds): static
    {
        $this->debounce = $milliseconds;

        return $this;
    }
}
