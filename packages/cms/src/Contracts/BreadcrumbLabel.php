<?php

namespace Coda\Cms\Contracts;

interface BreadcrumbLabel
{
    public function cmsBreadcrumbLabel(): string;

    /**
     * Optional route override for this breadcrumb.
     * Return null to render the label without a link, or
     * ['name' => string, 'parameters' => array] to link it.
     *
     * @return array{name: string, parameters?: array<string, mixed>}|null
     */
    public function cmsBreadcrumbRoute(): ?array;
}
