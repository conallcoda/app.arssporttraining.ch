<?php

namespace App\Console\Commands;

use Coda\Cms\Support\ColorPalette;
use Illuminate\Console\Command;

class GenerateColorSafelist extends Command
{
    protected $signature = 'color:safelist';

    protected $description = 'Generate @source inline() directive from ColorPalette methods';

    public function handle(): int
    {
        $safelist = ColorPalette::safelist();
        $classes = preg_split('/\s+/', $safelist);

        $this->info('Generated '.count($classes).' unique classes from ColorPalette.');
        $this->newLine();
        $this->line('@source inline("'.$safelist.'");');

        return self::SUCCESS;
    }
}
