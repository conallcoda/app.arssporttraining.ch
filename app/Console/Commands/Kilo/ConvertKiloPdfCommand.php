<?php

namespace App\Console\Commands\Kilo;

use Illuminate\Console\Command;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ConvertKiloPdfCommand extends Command
{
    protected $signature = 'kilo:convert';

    protected $description = 'Convert the Kilo PDF to HTML';

    public function handle(): int
    {
        $binary = (new ExecutableFinder)->find('pdftohtml');

        if (! $binary) {
            $this->error('pdftohtml not found. Install it with: brew install poppler');

            return 1;
        }

        $pdfPath = base_path('import/kilo/kilo.pdf');

        if (! file_exists($pdfPath)) {
            $this->error("PDF not found at: {$pdfPath}");

            return 1;
        }

        $outputPath = base_path('import/kilo/kilo.html');

        $this->info('Converting PDF to HTML...');

        $process = new Process([$binary, '-noframes', $pdfPath, $outputPath]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Conversion failed: '.$process->getErrorOutput());

            return 1;
        }

        $this->info("Done. Output: {$outputPath}");

        return 0;
    }
}
