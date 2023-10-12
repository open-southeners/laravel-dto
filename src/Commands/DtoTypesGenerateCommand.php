<?php

namespace OpenSoutheners\LaravelDto\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSoutheners\LaravelDto\DataTransferObject;
use OpenSoutheners\LaravelDto\TypeGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'dto:typescript')]
class DtoTypesGenerateCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'dto:typescript';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates TypeScript types from data transfer objects.';

    public function __construct(protected Filesystem $filesystem)
    {
        parent::__construct();
    }

    /**
     * Handle command.
     */
    public function handle(): int
    {
        if (! $this->confirm('Are you sure you want to generate types from your data transfer objects?', $this->option('force'))) {
            return 1;
        }

        $sourceDirectory = app_path($this->option('source'));
        
        if (! file_exists($sourceDirectory) || ! is_dir($sourceDirectory)) {
            $this->error('Path does not exists');

            return 2;
        }

        $destinationDirectory = $this->option('output');

        if (
            (! file_exists($destinationDirectory) || ! $this->filesystem->isWritable($destinationDirectory))
            && ! $this->filesystem->makeDirectory($destinationDirectory, 493, true)
        ) {
            $this->error('Permissions error, cannot create a directory under the destination path');

            return 3;
        }

        $dataTransferObjects = Collection::make((new Finder)->files()->in($sourceDirectory))
            ->map(fn ($file) => $file->getBasename('.php'))
            ->sort()
            ->values()
            ->all();

        $namespace = str_replace(DIRECTORY_SEPARATOR, '\\', Str::replaceFirst(app_path(), 'App', $sourceDirectory));

        $garbageCollection = Collection::make([]);

        foreach ($dataTransferObjects as $dataTransferObject) {
            $dataTransferObjectClass = implode('\\', [$namespace, $dataTransferObject]);

            if (! class_exists($dataTransferObjectClass) || ! is_a($dataTransferObjectClass, DataTransferObject::class, true)) {
                continue;
            }

            (new TypeGenerator($dataTransferObjectClass, $garbageCollection))->generate();
        }

        $filename = $this->option('filename');
        $configFilename = config('data-transfer-objects.types_generation_file_name');

        if ($filename === 'types.ts' && $configFilename) {
            $filename = $configFilename;
        }

        $destinationFile = implode(DIRECTORY_SEPARATOR, [$destinationDirectory, $filename]);

        if (
            $this->filesystem->exists($destinationFile)
            && ! $this->confirm('Are you sure you want to overwrite the output file?', $this->option('force'))
        ) {
            return 0;
        }

        if (! $this->filesystem->put($destinationFile, $garbageCollection->join("\n\n"))) {
            $this->error('Something happened and types file could not be written');

            return 4;
        }

        $this->info("Types file successfully generated at \"{$destinationFile}\"");

        return 0;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Force running without asking anything'],
            ['replace', 'r', InputOption::VALUE_NONE, 'Replace existing types'],
            ['output', 'o', InputOption::VALUE_OPTIONAL, 'Destination folder where to place generated types', resource_path('types')],
            ['source', 's', InputOption::VALUE_OPTIONAL, 'Source folder where to look at for data transfer objects (must be relative to app folder)', 'DataTransferObjects'],
            ['filename', null, InputOption::VALUE_OPTIONAL, 'Destination file name with types generated on it', 'types.ts'],
        ];
    }
}
