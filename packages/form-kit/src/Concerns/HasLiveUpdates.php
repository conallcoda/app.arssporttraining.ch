<?php

namespace Coda\FormKit\Concerns;

trait HasLiveUpdates
{
    public bool $live = false;

    public bool $blur = true;

    public bool $change = false;

    public string $updateOn = 'blur';

    public ?int $debounce = null;

    public ?int $debounceMs = null;

    public function updateOn(string $mode, ?int $debounceMs = null): static
    {
        if (! in_array($mode, ['default', 'live', 'blur', 'change'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported update mode [%s].', $mode));
        }

        $this->updateOn = $mode;
        $this->debounceMs = $mode === 'live' ? $debounceMs : null;
        $this->syncUpdateFlags();

        return $this;
    }

    public function debounceUpdate(int $milliseconds): static
    {
        $this->updateOn = 'live';
        $this->debounceMs = $milliseconds;
        $this->syncUpdateFlags();

        return $this;
    }

    public function live(bool $live = true): static
    {
        if (! $live) {
            return $this->updateOn('default');
        }

        return $this->updateOn('live', $this->debounceMs);
    }

    public function blur(bool $blur = true): static
    {
        if (! $blur) {
            return $this->updateOn('default');
        }

        return $this->updateOn('blur');
    }

    public function change(bool $change = true): static
    {
        if (! $change) {
            return $this->updateOn('default');
        }

        return $this->updateOn('change');
    }

    public function debounce(int $milliseconds): static
    {
        return $this->debounceUpdate($milliseconds);
    }

    public function wireModelDirective(bool $disabled = false): string
    {
        if ($disabled) {
            return 'wire:model';
        }

        return match ($this->updateOn) {
            'default' => 'wire:model',
            'live' => $this->debounceMs !== null
                ? sprintf('wire:model.live.debounce.%dms', $this->debounceMs)
                : 'wire:model.live',
            'change' => 'wire:model.change.live',
            default => 'wire:model.blur.live',
        };
    }

    private function syncUpdateFlags(): void
    {
        $this->live = $this->updateOn === 'live';
        $this->blur = $this->updateOn === 'blur';
        $this->change = $this->updateOn === 'change';
        $this->debounce = $this->debounceMs;
    }
}
