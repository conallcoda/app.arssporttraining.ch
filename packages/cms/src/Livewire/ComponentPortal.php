<?php

namespace Coda\Cms\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ComponentPortal extends Component
{
    public array $stack = [];

    #[On('portal:open')]
    public function open(string $component, array $props = [], string $title = '', string $variant = 'flyout'): void
    {
        $id = uniqid('portal-');

        $this->stack[] = [
            'id' => $id,
            'component' => $component,
            'props' => $props,
            'title' => $title,
            'variant' => $variant,
        ];
    }

    #[On('portal:close')]
    public function close(?string $id = null): void
    {
        if ($id) {
            $this->stack = array_values(array_filter($this->stack, fn (array $item) => $item['id'] !== $id));
        } else {
            array_pop($this->stack);
        }
    }

    public function removeFromStack(string $id): void
    {
        $this->stack = array_values(array_filter($this->stack, fn (array $item) => $item['id'] !== $id));
    }

    public function render(): View
    {
        return view('cms::component-portal');
    }
}
