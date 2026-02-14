<?php

namespace App\Data\Exercise;

use App\Models\Exercise\Exercise;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class ExerciseImportData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id = null,
        public ?int $externalId = null,
        public string $name = '',
        public ?int $category = null,
        public array $equipment = [],
        public array $modifiers = [],
        public ?string $videoUrl = null,
        public ?string $instructions = null,
        public ExerciseConfig $config = new ExerciseConfig,
    ) {}

    public function persist(): void
    {
        $exerciseData = ExerciseData::fromImport($this);
        $exerciseData->persist();

        $this->id = $exerciseData->id;

        if ($this->externalId) {
            Exercise::where('id', $this->id)->update(['external_id' => $this->externalId]);
        }
    }

    public static function getForm(): Form
    {
        return ExerciseData::getForm();
    }
}
