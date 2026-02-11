<?php

namespace App\Console\Commands\Kilo;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Console\Command;
use Symfony\Component\VarExporter\VarExporter;

class ParseKiloPdfCommand extends Command
{
    protected $signature = 'kilo:parse';

    protected $description = 'Parse the Kilo PDF HTML export and extract exercises with YouTube links';

    private const BODY_AREAS = ['Upper Body', 'Lower Body'];

    private const CATEGORIES = [
        'Overhead Press', 'Incline Press', 'Flat Press', 'Decline Press',
        'Push-Up', 'Chin-Up', 'Pull-Up', 'Row', 'Pulldown', 'Front Lever',
        'Triceps', 'Biceps', 'Lateral Deltoid', 'Posterior Deltoid', 'Pectorals',
        'Upper Back', 'External Rotators', 'Scapular Retractors', 'Shoulder Flexion',
        'Neck', 'Forearms',
        'Squat', 'Deadlift', 'Knee Extension', 'Knee Flexion', 'Hip Extension',
        'Calves', 'Abdominals',
    ];

    private const EQUIPMENT_TAGS = [
        'BB', 'DB', 'EZ Bar', 'Cambered Bar', 'Thick Bar',
        'Thick Angled Bar', 'Thick EZ Bar', 'Thick Multi-Angled Bar',
        'Thick Parallel Bar', 'Triceps Bar', 'Safety Bar', 'Trap Bar',
        'Swiss Ball', 'Med Ball', 'Foam Roller', 'Rope', 'Rings',
        'Band', 'Bands', 'Machine',
        'Head Harness', 'Shoulder Horn',
        "Thor's Hammer",
    ];

    private const SUBCATEGORIES = [
        'Arm to Side', 'Arm in Front', 'Arm Above', 'Arm Behind',
        'Arm Adducted to Side', 'Arm Abducted in Front', 'Arm Abducted to Side',
        'Scapular Adduction', 'Scapular Abduction', 'Horizontal Abduction',
        'Grip', 'Wrist', 'Forearm Flexors', 'Forearm Extensors',
        'Long Range', 'Short Range',
        'Split Squat', 'Single-Leg Squat', 'Lunge', 'Step-Up', 'Leg Machine',
        'Stabilization', 'Leg Curl', 'Glute Ham Raise', 'Nordic Curl',
        'Bottom Range', 'Mid Range', 'Top Range',
        'Soleus', 'Anterior Tibialis', 'Gastrocnemius',
        'Rotation', 'Flexion',
    ];

    private ?string $bodyArea = null;

    private ?string $category = null;

    private ?string $subcategory = null;

    private array $exercises = [];

    private array $categories = [];

    private array $tags = [];

    public function handle(): int
    {
        $htmlPath = base_path('import/kilo/kilo.html');

        if (! file_exists($htmlPath)) {
            $this->error("HTML file not found at: {$htmlPath}");
            $this->error('Run php artisan kilo:convert first.');

            return 1;
        }

        $html = file_get_contents($htmlPath);
        $pages = explode('<hr/>', $html);

        $this->info('Parsing '.count($pages).' pages...');

        $progressBar = $this->output->createProgressBar(count($pages) - 3);
        $progressBar->start();

        for ($i = 3; $i < count($pages); $i++) {
            $this->parsePage($pages[$i]);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->applyEquipmentOverrides();

        $exercisesNoEquipment = array_values(array_map(
            fn (array $exercise) => [
                'name' => $exercise['full_name'],
                'categories' => $exercise['categories'],
                'url' => $exercise['url'],
            ],
            array_filter($this->exercises, fn (array $exercise) => empty($exercise['equipment']) && ! $exercise['bodyweight']),
        ));

        $exercisesNoEquipmentSimple = array_values(array_map(
            fn (array $exercise) => $exercise['full_name'],
            array_filter($this->exercises, fn (array $exercise) => empty($exercise['equipment']) && ! $exercise['bodyweight']),
        ));

        $outputPath = base_path('import/kilo/exercises.php');
        $exported = VarExporter::export([
            'categories' => $this->categories,
            'exercises' => $this->exercises,
            'tags' => array_values(array_unique($this->tags)),
            'exercises_no_equipment' => $exercisesNoEquipment,
            'exercises_no_equipment_simple' => $exercisesNoEquipmentSimple,
        ]);
        file_put_contents($outputPath, "<?php\n\nreturn {$exported};\n");

        $this->displayStats();
        $this->info("Output: {$outputPath}");

        return 0;
    }

    private function parsePage(string $pageHtml): void
    {
        $pageHtml = preg_replace('/<img[^>]*>/i', '', $pageHtml);
        $pageHtml = preg_replace('/<a\s+name=\d+\s*>\s*<\/a>/i', '', $pageHtml);

        $elements = $this->extractElements($pageHtml);

        foreach ($elements as $el) {
            if ($el['type'] === 'text') {
                $this->handleText($el['text']);
            } elseif ($el['type'] === 'exercise') {
                $this->addExercise($el['name'], $el['url']);
            }
        }
    }

    private function extractElements(string $html): array
    {
        $elements = [];

        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

        $body = $doc->getElementsByTagName('body')->item(0);

        if ($body) {
            $this->walkNodes($body, $elements);
        }

        return $elements;
    }

    private function walkNodes(DOMNode $node, array &$elements): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = $this->cleanText($child->textContent);

                if ($text !== '' && ! str_starts_with($text, '©')) {
                    $elements[] = ['type' => 'text', 'text' => $text];
                }
            } elseif ($child instanceof DOMElement) {
                match ($child->tagName) {
                    'a' => $this->handleLink($child, $elements),
                    'img' => null,
                    default => $this->walkNodes($child, $elements),
                };
            }
        }
    }

    private function handleLink(DOMElement $element, array &$elements): void
    {
        $href = $element->getAttribute('href');

        if (! str_starts_with($href, 'https://youtu.be/')) {
            return;
        }

        $name = $this->cleanText($element->textContent);

        if ($name !== '') {
            $elements[] = ['type' => 'exercise', 'name' => $name, 'url' => $href];
        }
    }

    private function handleText(string $text): void
    {
        if (in_array($text, self::BODY_AREAS)) {
            $this->bodyArea = $text;
            $this->category = null;
            $this->subcategory = null;
        } elseif (in_array($text, self::CATEGORIES)) {
            $this->category = $text;
            $this->subcategory = null;
        } elseif (in_array($text, self::SUBCATEGORIES)) {
            $this->subcategory = $text;
        }
    }

    private function addExercise(string $name, string $url): void
    {
        if (! $this->bodyArea || ! $this->category) {
            return;
        }

        $parts = array_map('trim', explode(' - ', $name));
        $exerciseTags = array_slice($parts, 1);

        $categories = array_filter([$this->bodyArea, $this->category, $this->subcategory]);

        $classified = $this->classifyTags($exerciseTags);

        $this->exercises[] = [
            'full_name' => $name,
            'short_name' => $parts[0],
            'tags' => $exerciseTags,
            'equipment' => $classified['equipment'],
            'variants' => $classified['variants'],
            'bodyweight' => false,
            'categories' => array_values($categories),
            'url' => $url,
        ];

        array_push($this->tags, ...$exerciseTags);

        $this->buildCategoryTree();
    }

    private function buildCategoryTree(): void
    {
        if (! isset($this->categories[$this->bodyArea])) {
            $this->categories[$this->bodyArea] = [];
        }

        if (! isset($this->categories[$this->bodyArea][$this->category])) {
            $this->categories[$this->bodyArea][$this->category] = [];
        }

        if ($this->subcategory && ! in_array($this->subcategory, $this->categories[$this->bodyArea][$this->category])) {
            $this->categories[$this->bodyArea][$this->category][] = $this->subcategory;
        }
    }

    /**
     * @return array{equipment: string[], variants: string[]}
     */
    private function classifyTags(array $tags): array
    {
        $equipment = [];
        $variants = [];

        foreach ($tags as $tag) {
            if (in_array($tag, self::EQUIPMENT_TAGS)) {
                $equipment[] = $tag;
            } else {
                $variants[] = $tag;
            }
        }

        return [
            'equipment' => array_values(array_unique($equipment)),
            'variants' => $variants,
        ];
    }

    private function applyEquipmentOverrides(): void
    {
        $overridesPath = base_path('import/kilo/exercise_details.php');

        if (! file_exists($overridesPath)) {
            return;
        }

        $overrides = require $overridesPath;
        $equipmentNameRules = $overrides['equipment']['name_rules'] ?? [];
        $equipmentExercises = $overrides['equipment']['exercises'] ?? [];
        $bodyweightNameRules = $overrides['bodyweight']['name_rules'] ?? [];
        $bodyweightExercises = $overrides['bodyweight']['exercises'] ?? [];

        foreach ($this->exercises as &$exercise) {
            foreach ($equipmentNameRules as $needle => $equipment) {
                if (str_contains($exercise['full_name'], $needle) && ! in_array($equipment, $exercise['equipment'])) {
                    $exercise['equipment'][] = $equipment;
                }
            }

            if (isset($equipmentExercises[$exercise['full_name']]) && ! in_array($equipmentExercises[$exercise['full_name']], $exercise['equipment'])) {
                $exercise['equipment'][] = $equipmentExercises[$exercise['full_name']];
            }

            foreach ($bodyweightNameRules as $needle) {
                if (str_contains($exercise['full_name'], $needle)) {
                    $exercise['bodyweight'] = true;
                    break;
                }
            }

            if (in_array($exercise['full_name'], $bodyweightExercises)) {
                $exercise['bodyweight'] = true;
            }
        }

        unset($exercise);
    }

    private function cleanText(string $text): string
    {
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    private function displayStats(): void
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('PARSE COMPLETE');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        foreach ($this->categories as $bodyArea => $categories) {
            $this->line("  {$bodyArea}: ".count($categories).' categories');
        }

        $withEquipment = count(array_filter($this->exercises, fn (array $e) => ! empty($e['equipment']) && ! $e['bodyweight']));
        $bodyweight = count(array_filter($this->exercises, fn (array $e) => $e['bodyweight']));
        $withoutEquipment = count(array_filter($this->exercises, fn (array $e) => empty($e['equipment']) && ! $e['bodyweight']));

        $this->newLine();
        $this->line('  Total exercises: '.count($this->exercises));
        $this->line('  With equipment: '.$withEquipment);
        $this->line('  Bodyweight: '.$bodyweight);
        $this->line('  Without equipment: '.$withoutEquipment);
        $this->line('  Unique tags: '.count(array_unique($this->tags)));
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
    }
}
