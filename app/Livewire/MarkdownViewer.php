<?php

namespace App\Livewire;

use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MarkdownViewer extends Component
{
    public string $filePath = '';

    public string $content = '';

    public bool $inline = false;

    public function mount(string $filePath = '', string $content = '', bool $inline = false): void
    {
        $this->filePath = $filePath;
        $this->content = $content;
        $this->inline = $inline;
    }

    #[Computed]
    public function markdown(): string
    {
        $raw = $this->content;

        if ($this->filePath && file_exists($this->filePath)) {
            $raw = file_get_contents($this->filePath);
        }

        return $raw;
    }

    #[Computed]
    public function topLevelSections(): array
    {
        $markdown = $this->markdown;
        $lines = explode("\n", $markdown);
        $sections = [];
        $currentSection = null;
        $currentContent = [];
        $h2Counter = 0;

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,2})\s+(.+)$/', $line, $matches)) {
                if ($currentSection !== null) {
                    $currentSection['content'] = implode("\n", $currentContent);
                    $sections[] = $currentSection;
                }

                $level = strlen($matches[1]);
                $title = trim($matches[2]);
                $slug = Str::slug($title);

                $number = null;
                if ($level === 2) {
                    $h2Counter++;
                    $number = (string) $h2Counter;
                }

                $currentSection = [
                    'level' => $level,
                    'title' => $title,
                    'slug' => $slug,
                    'number' => $number,
                    'h2Index' => $level === 2 ? $h2Counter : null,
                    'content' => '',
                ];
                $currentContent = [];
            } else {
                $currentContent[] = $line;
            }
        }

        if ($currentSection !== null) {
            $currentSection['content'] = implode("\n", $currentContent);
            $sections[] = $currentSection;
        }

        return $sections;
    }

    #[Computed]
    public function tableOfContents(): array
    {
        $markdown = $this->markdown;
        $lines = explode("\n", $markdown);
        $toc = [];
        $counters = [0, 0, 0, 0, 0, 0];

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $title = trim($matches[2]);
                $slug = Str::slug($title);

                $number = null;
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
                }

                $toc[] = [
                    'level' => $level,
                    'title' => $title,
                    'slug' => $slug,
                    'number' => $number,
                ];
            }
        }

        return $toc;
    }

    public function renderMarkdown(string $content, int $h2Index): string
    {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = $converter->convert($content)->getContent();

        $h3Counter = 0;
        $h4Counter = 0;

        $html = preg_replace_callback(
            '/<h([3-6])>(.+?)<\/h[3-6]>/i',
            function ($matches) use ($h2Index, &$h3Counter, &$h4Counter) {
                $level = (int) $matches[1];
                $text = strip_tags($matches[2]);
                $slug = Str::slug($text);

                $number = '';
                if ($level === 3) {
                    $h3Counter++;
                    $h4Counter = 0;
                    $number = $h2Index.'.'.$h3Counter;
                } elseif ($level === 4) {
                    $h4Counter++;
                    $number = $h2Index.'.'.$h3Counter.'.'.$h4Counter;
                }

                $numberSpan = $number ? '<span class="text-zinc-400 mr-2">'.$number.'</span>' : '';

                return '<h'.$level.' id="'.$slug.'">'.$numberSpan.$matches[2].'</h'.$level.'>';
            },
            $html
        );

        return $html;
    }

    public function render()
    {
        return view('livewire.markdown-viewer');
    }
}
