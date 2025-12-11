<?php

namespace App\Livewire;

use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CalculatorDocumentation extends Component
{
    private array $docToc = [];

    private string $docHtml = '';

    protected function parseDocumentation(): void
    {
        if ($this->docHtml !== '') {
            return;
        }

        $filePath = base_path('docs/training-plans.md');

        if (! file_exists($filePath)) {
            return;
        }

        $markdown = file_get_contents($filePath);

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

                return '<h'.$level.' id="'.$slug.'" class="scroll-mt-16">'.$numberHtml.$matches[2].'</h'.$level.'>';
            },
            $html
        );

        $this->docToc = $toc;
        $this->docHtml = $html;
    }

    #[Computed]
    public function toc(): array
    {
        $this->parseDocumentation();

        return $this->docToc;
    }

    #[Computed]
    public function html(): string
    {
        $this->parseDocumentation();

        return $this->docHtml;
    }

    public function render()
    {
        return view('livewire.calculator-documentation');
    }
}
