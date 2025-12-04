<?php

namespace App\Livewire;

use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SimpleMarkdownViewer extends Component
{
    public string $filePath = '';

    private array $parsedToc = [];

    private string $parsedHtml = '';

    public function mount(string $filePath = ''): void
    {
        $this->filePath = $filePath;
    }

    protected function parseMarkdown(): void
    {
        if ($this->parsedHtml !== '') {
            return;
        }

        if (! $this->filePath || ! file_exists($this->filePath)) {
            return;
        }

        $markdown = file_get_contents($this->filePath);

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = $converter->convert($markdown)->getContent();

        $toc = [];
        $counters = [0, 0, 0, 0, 0, 0];

        $html = preg_replace_callback(
            '/<h([1-6])>(.+?)<\/h[1-6]>/i',
            function ($matches) use (&$counters, &$toc) {
                $level = (int) $matches[1];
                $text = strip_tags($matches[2]);
                $slug = Str::slug($text);

                $numberHtml = '';
                $number = '';
                if ($level >= 2) {
                    $counters[$level - 1]++;
                    for ($i = $level; $i < 6; $i++) {
                        $counters[$i] = 0;
                    }

                    $numberParts = [];
                    for ($i = 1; $i < $level; $i++) {
                        $numberParts[] = $counters[$i];
                    }
                    $number = implode('.', $numberParts);
                    $numberHtml = '<span class="text-zinc-400 dark:text-zinc-500 mr-2">'.$number.'</span>';

                    if ($level <= 3) {
                        $toc[] = [
                            'level' => $level,
                            'title' => $text,
                            'slug' => $slug,
                            'number' => $number,
                        ];
                    }
                }

                return '<h'.$level.' id="'.$slug.'" class="scroll-mt-4">'.$numberHtml.$matches[2].'</h'.$level.'>';
            },
            $html
        );

        $this->parsedToc = $toc;
        $this->parsedHtml = $html;
    }

    #[Computed]
    public function tableOfContents(): array
    {
        $this->parseMarkdown();

        return $this->parsedToc;
    }

    #[Computed]
    public function html(): string
    {
        $this->parseMarkdown();

        return $this->parsedHtml;
    }

    public function render()
    {
        return view('livewire.simple-markdown-viewer');
    }
}
