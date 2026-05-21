<?php

namespace Coda\Cms\Layout;

class InfoBox
{
    public ?string $text = null;

    public ?string $view = null;

    /**
     * @var array<string, mixed>
     */
    public array $viewData = [];

    public string $tone = 'info';

    public static function make(?string $text = null): static
    {
        $instance = new static;
        $instance->text = $text;

        return $instance;
    }

    public function text(?string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function view(string $view, array $viewData = []): static
    {
        $this->view = $view;
        $this->viewData = $viewData;

        return $this;
    }

    public function tone(string $tone): static
    {
        $this->tone = $tone;

        return $this;
    }

    public function info(): static
    {
        return $this->tone('info');
    }

    public function warn(): static
    {
        return $this->tone('warn');
    }

    public function danger(): static
    {
        return $this->tone('danger');
    }
}
