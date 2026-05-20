<?php

namespace Coda\Cms\Display\Concerns;

trait HasHelpText
{
    public ?string $helpText = null;

    public ?string $helpTitle = null;

    public function help(string $text, ?string $title = null): static
    {
        $this->helpText = $text;
        $this->helpTitle = $title;

        return $this;
    }

    public function hasHelp(): bool
    {
        return is_string($this->helpText) && trim($this->helpText) !== '';
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function getHelpTitle(): string
    {
        return $this->helpTitle ?: $this->getDisplayLabel();
    }
}
