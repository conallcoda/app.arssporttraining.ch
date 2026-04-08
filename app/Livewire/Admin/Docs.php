<?php

namespace App\Livewire\Admin;

use App\Services\OrgChart\OrgChartParser;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Docs extends Component
{
    #[Url]
    public string $system = '';

    public function mount(): void
    {
        if ($this->system === '') {
            $this->system = array_key_first($this->getSystems());
        }
    }

    public function render(): View
    {
        $systems = $this->getSystems();
        $current = $systems[$this->system] ?? reset($systems);

        $trees = [];
        $errors = [];
        $tabs = [];

        foreach ($current['tabs'] as $key => $tab) {
            $loaded = $this->loadTree($current['path'].'/'.$tab['file']);
            $trees[$key] = $loaded['tree'];
            $errors[$key] = $loaded['error'];
            $tabs[$key] = $tab['label'];
        }

        return view('livewire.admin.docs', [
            'systems' => collect($systems)->map(fn (array $s) => $s['label']),
            'tabs' => $tabs,
            'trees' => $trees,
            'errors' => $errors,
        ])->layout('cms::components.layouts.docs', ['title' => 'Docs']);
    }

    protected function getSystems(): array
    {
        $basePath = __DIR__.'/trees';
        $systems = [];

        foreach (File::directories($basePath) as $dir) {
            $dataFile = $dir.'/data.php';

            if (! file_exists($dataFile)) {
                continue;
            }

            $data = require $dataFile;
            $key = basename($dir);
            $systems[$key] = array_merge($data, ['path' => $dir]);
        }

        return $systems;
    }

    protected function loadTree(string $path): array
    {
        try {
            return [
                'tree' => OrgChartParser::parseFile($path),
                'error' => null,
            ];
        } catch (\InvalidArgumentException $exception) {
            return [
                'tree' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }
}
