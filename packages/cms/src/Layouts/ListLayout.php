<?php

namespace Coda\Cms\Layouts;

use Coda\Cms\Display\Pagination;

final class ListLayout
{
    private ?TableLayout $table = null;

    private ?CardLayout $cards = null;

    private ?Pagination $pagination = null;

    private ?string $defaultView = null;

    private bool $showViewToggle = true;

    public static function make(): static
    {
        return new static;
    }

    public function table(TableLayout $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function cards(CardLayout $cards): static
    {
        $this->cards = $cards;

        return $this;
    }

    public function pagination(Pagination $pagination): static
    {
        $this->pagination = $pagination;

        return $this;
    }

    public function defaultView(string $view): static
    {
        $this->defaultView = $view;

        return $this;
    }

    public function showViewToggle(bool $show = true): static
    {
        $this->showViewToggle = $show;

        return $this;
    }

    public function hideViewToggle(): static
    {
        return $this->showViewToggle(false);
    }

    public function getTable(): ?TableLayout
    {
        return $this->table;
    }

    public function getCards(): ?CardLayout
    {
        return $this->cards;
    }

    public function getPagination(): ?Pagination
    {
        return $this->pagination;
    }

    public function getDefaultView(): ?string
    {
        return $this->defaultView;
    }

    public function shouldShowViewToggle(): bool
    {
        return $this->showViewToggle;
    }

    public function getFacets(): array
    {
        return $this->table?->getFacets() ?? [];
    }
}
