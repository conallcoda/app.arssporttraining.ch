<?php

namespace App\Services\OrgChart;

use Coda\Cms\Support\ColorPalette;

/**
 * OrgChartParser
 *
 * Parses a plain-text indented tree definition into a nested array
 * suitable for rendering with the docs org chart renderer.
 *
 * Format rules:
 *   - Each line is a node label
 *   - Indentation (tabs or spaces) defines parent/child relationships
 *   - Append [color] after a label to set a named color: Coach Dashboard[blue]
 *   - Use [ref:folder.file.Node] to inject a node from another tree file
 *   - Blank lines are ignored
 *   - Comments start with # and are ignored
 *
 * Named colors: blue, orange, green, purple, teal, red, pink, gray
 * Or use any hex value: Coach Dashboard[#FF5733]
 */
class OrgChartParser
{
    protected const INHERITED_FILL_STEPS = [50, 100, 100, 200, 200, 300];

    protected const INHERITED_STROKE_STEPS = [500, 400, 400, 500, 500, 600];

    protected const INHERITED_TEXT_STEPS = [900, 800, 800, 900, 900, 950];

    protected ?string $basePath = null;

    protected array $parseStack = [];

    // -------------------------------------------------------------------------

    public static function parse(string $text): array
    {
        return (new static)->parseText($text);
    }

    public static function parseFile(string $path): array
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $instance = new static;
        $instance->basePath = dirname(dirname($path));
        $instance->parseStack[] = realpath($path);

        $tree = $instance->parseText(file_get_contents($path));

        array_pop($instance->parseStack);

        return $tree;
    }

    // -------------------------------------------------------------------------

    protected function parseText(string $text): array
    {
        $lines = explode("\n", $text);
        $lines = $this->filterLines($lines);

        if (empty($lines)) {
            return [];
        }

        $flat = array_map(fn ($line, $index) => [
            'depth' => $this->detectDepth($line, $index + 1),
            'node' => $this->parseLine(trim($line)),
        ], $lines, array_keys($lines));

        $root = $this->buildStructure($flat);
        $root = $this->resolveReferences($root);
        $root = $this->applyInheritedColors($root);
        $root = $this->applyNumbers($root);

        if (count($root['children']) === 1) {
            return $root['children'][0];
        }

        $root['label'] = 'All';

        return $root;
    }

    protected function filterLines(array $lines): array
    {
        return array_filter($lines, function (string $line) {
            $trimmed = trim($line);

            return $trimmed !== '' && ! str_starts_with($trimmed, '#');
        });
    }

    protected function detectDepth(string $line, int $lineNumber): int
    {
        preg_match('/^[ \t]*/', $line, $matches);
        $leading = $matches[0] ?? '';

        if ($leading === '') {
            return 0;
        }

        $snippet = str_replace("\t", '\t', rtrim($line, "\r\n"));

        if (str_contains($leading, "\t") && str_contains($leading, ' ')) {
            throw new \InvalidArgumentException(
                "Invalid indentation on line {$lineNumber}. Use exactly 1 tab or exactly 4 spaces per level, but do not mix them. Offending line: {$snippet}",
            );
        }

        if (str_contains($leading, "\t")) {
            return substr_count($leading, "\t");
        }

        if (strlen($leading) % 4 !== 0) {
            throw new \InvalidArgumentException(
                "Invalid indentation on line {$lineNumber}. Use exactly 1 tab or exactly 4 spaces per level. Offending line: {$snippet}",
            );
        }

        return (int) (strlen($leading) / 4);
    }

    protected function parseLine(string $line): array
    {
        [$line, $description] = $this->extractDescription($line);
        [$label, $directives] = $this->extractDirectives($line);

        $node = ['label' => $label];

        if ($description !== null) {
            $node['description'] = $description;
        }

        if (isset($directives['color'])) {
            $node = array_merge($node, $this->resolveColor($directives['color']));
        }

        if (isset($directives['ref'])) {
            $node['ref'] = $directives['ref'];
        }

        return $node;
    }

    protected function extractDescription(string $line): array
    {
        $length = strlen($line);
        $bracketDepth = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];

            if ($char === '[') {
                $bracketDepth++;
                continue;
            }

            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                continue;
            }

            if ($char === ':' && $bracketDepth === 0) {
                return [
                    trim(substr($line, 0, $i)),
                    trim(substr($line, $i + 1)) ?: null,
                ];
            }
        }

        return [trim($line), null];
    }

    protected function extractDirectives(string $line): array
    {
        $directives = [];
        $label = trim($line);

        while (preg_match('/^(.*?)\[([^\]]+)\]\s*$/', $label, $matches)) {
            $label = trim($matches[1]);
            $directive = trim($matches[2]);

            if (str_contains($directive, ':')) {
                [$key, $value] = array_map('trim', explode(':', $directive, 2));
                $directives[strtolower($key)] = $value;
                continue;
            }

            $directives['color'] = $directive;
        }

        return [$label, $directives];
    }

    protected function resolveColor(string $color): array
    {
        $key = strtolower($color);

        if (array_key_exists($key, ColorPalette::COLORS)) {
            return ['palette' => $key];
        }

        return [
            'fill' => $color,
            'stroke' => $color,
            'text' => '#000000',
        ];
    }

    protected function buildStructure(array $flat): array
    {
        $root = ['label' => '__root__', 'children' => []];
        $stack = [&$root];

        foreach ($flat as $item) {
            $depth = $item['depth'];
            $node = $item['node'];
            $node['children'] = [];

            while (count($stack) > $depth + 1) {
                array_pop($stack);
            }

            $parent = &$stack[count($stack) - 1];
            $parent['children'][] = $node;

            $stack[] = &$parent['children'][count($parent['children']) - 1];
        }

        return $root;
    }

    // -------------------------------------------------------------------------
    // Reference resolution
    // -------------------------------------------------------------------------

    protected function resolveReferences(array $node): array
    {
        $children = [];

        foreach ($node['children'] ?? [] as $child) {
            if (isset($child['ref'])) {
                $resolved = $this->resolveRef($child['ref']);
                $children[] = $this->resolveReferences($resolved);

                continue;
            }

            $children[] = $this->resolveReferences($child);
        }

        $node['children'] = $children;

        return $node;
    }

    protected function resolveRef(string $ref): array
    {
        if (! $this->basePath) {
            throw new \InvalidArgumentException("Cannot resolve ref '{$ref}': no base path set. Use parseFile() instead of parse().");
        }

        $parts = explode('.', $ref);

        if (count($parts) < 2) {
            throw new \InvalidArgumentException("Invalid ref '{$ref}': expected folder.file or folder.file.Node");
        }

        $folder = $parts[0];
        $file = $parts[1];
        $nodeLabel = $parts[2] ?? null;

        $path = $this->basePath.'/'.$folder.'/'.$file.'.txt';
        $realPath = realpath($path);

        if (! $realPath || ! file_exists($realPath)) {
            throw new \InvalidArgumentException("Referenced file not found: {$path}");
        }

        if (in_array($realPath, $this->parseStack)) {
            throw new \InvalidArgumentException("Circular reference detected: {$ref}");
        }

        $tree = $this->parseFileRaw($realPath);

        if ($nodeLabel === null) {
            return $tree;
        }

        $found = $this->findNode($tree, $nodeLabel);

        if (! $found) {
            throw new \InvalidArgumentException("Node '{$nodeLabel}' not found in {$path}");
        }

        return $found;
    }

    protected function parseFileRaw(string $path): array
    {
        $this->parseStack[] = $path;

        $lines = explode("\n", file_get_contents($path));
        $lines = $this->filterLines($lines);

        $flat = array_map(fn ($line, $index) => [
            'depth' => $this->detectDepth($line, $index + 1),
            'node' => $this->parseLine(trim($line)),
        ], $lines, array_keys($lines));

        $root = $this->buildStructure($flat);
        $root = $this->resolveReferences($root);

        array_pop($this->parseStack);

        if (count($root['children']) === 1) {
            return $root['children'][0];
        }

        $root['label'] = 'All';

        return $root;
    }

    protected function findNode(array $tree, string $label): ?array
    {
        if ($tree['label'] === $label) {
            return $tree;
        }

        foreach ($tree['children'] ?? [] as $child) {
            $found = $this->findNode($child, $label);

            if ($found) {
                return $found;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------

    protected function applyInheritedColors(array $node, ?string $inheritedPalette = null, int $paletteDepth = 0): array
    {
        $palette = $node['palette'] ?? $inheritedPalette;
        $depthFromPalette = isset($node['palette']) ? 0 : $paletteDepth;

        if ($palette && ! isset($node['fill'], $node['stroke'], $node['text'])) {
            $node = array_merge($node, $this->paletteVariant($palette, $depthFromPalette));
        }

        $children = [];

        foreach ($node['children'] ?? [] as $child) {
            $children[] = $this->applyInheritedColors(
                $child,
                $palette,
                $palette ? $depthFromPalette + 1 : 0,
            );
        }

        $node['children'] = $children;
        unset($node['palette']);

        return $node;
    }

    protected function paletteVariant(string $palette, int $depthFromPalette): array
    {
        $index = min($depthFromPalette, count(self::INHERITED_FILL_STEPS) - 1);
        $fillStep = self::INHERITED_FILL_STEPS[$index];
        $strokeStep = self::INHERITED_STROKE_STEPS[$index];
        $textStep = self::INHERITED_TEXT_STEPS[$index];

        return [
            'fill' => "var(--color-{$palette}-{$fillStep})",
            'stroke' => "var(--color-{$palette}-{$strokeStep})",
            'text' => "var(--color-{$palette}-{$textStep})",
        ];
    }

    protected function applyNumbers(array $node, ?string $prefix = null): array
    {
        $children = [];

        foreach ($node['children'] ?? [] as $index => $child) {
            $number = $prefix ? "{$prefix}".($index + 1).'.' : ($index + 1).'.';
            $child['number'] = $number;
            $children[] = $this->applyNumbers($child, $number);
        }

        $node['children'] = $children;

        return $node;
    }
}
