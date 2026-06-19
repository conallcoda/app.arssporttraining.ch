<?php

namespace Coda\Cms\Layouts;

use Coda\Cms\Layout\InfoBox;

final class DetailsTab
{
    private array $left = [];

    private array $right = [];

    private mixed $infoBox = null;

    public function __construct(
        private readonly string $title,
    ) {}

    public static function make(string $title): static
    {
        return new static($title);
    }

    public function left(array $left): static
    {
        $this->left = array_values($left);

        return $this;
    }

    public function right(array $right): static
    {
        $this->right = array_values($right);

        return $this;
    }

    public function infoBox(mixed $infoBox): static
    {
        $this->infoBox = $infoBox;

        return $this;
    }

    public function infoText(string $text, string $tone = 'info'): static
    {
        return $this->infoBox(
            InfoBox::make($text)->tone($tone)
        );
    }

    public function infoView(string $view, array $viewData = [], string $tone = 'info'): static
    {
        return $this->infoBox(
            InfoBox::make()->tone($tone)->view($view, $viewData)
        );
    }

    public function title(): string
    {
        return $this->title;
    }

    public function getLeft(): array
    {
        return $this->left;
    }

    public function getRight(): array
    {
        return $this->right;
    }

    public function getInfoBox(): mixed
    {
        return $this->infoBox;
    }
}
