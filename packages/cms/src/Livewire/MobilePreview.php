<?php

namespace Coda\Cms\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class MobilePreview extends Component
{
    public string $url = '';

    public string $device = 'mobile';

    public function mount(): void
    {
        if (! config('cms.mobile_preview')) {
            abort(403);
        }

        $this->url = request()->query('url', '/');
        $this->device = request()->query('device', 'mobile');
    }

    public function iframeUrl(): string
    {
        $parsed = parse_url($this->url);
        $query = [];

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $query['_preview'] = '1';

        $base = ($parsed['path'] ?? '/');

        return $base.'?'.http_build_query($query);
    }

    public function render(): View
    {
        return view('cms::livewire.mobile-preview')
            ->layout('cms::components.layouts.preview')
            ->title('Mobile Preview');
    }
}
