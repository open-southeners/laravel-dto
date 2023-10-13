<?php

namespace OpenSoutheners\LaravelDto\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use OpenSoutheners\LaravelDto\DataTransferObject;
use OpenSoutheners\LaravelDto\TypeGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'dto:typescript')]
class DtoTypescriptGenerateCommand extends Command
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

    protected const OPTION_DEFAULT_OUTPUT = 'js';

    protected const OPTION_DEFAULT_SOURCE = 'DataTransferObjects';
    
    protected const OPTION_DEFAULT_FILENAME = 'types';

    public function __construct(protected Filesystem $filesystem)
    {
        parent::__construct();
    }

    /**
     * Handle command.
     */
    public function handle(): int
    {
        [
            'force' => $force,
            'output' => $outputDirectory,
            'source' => $sourceDirectory,
            'filename' => $outputFilename,
            'declarations' => $typesAsDeclarations,
        ] = $this->getOptionsWithDefaults();

        if (! $this->confirm('Are you sure you want to generate types from your data transfer objects?', $force)) {
            return 1;
        }

        if (! file_exists($sourceDirectory) || ! is_dir($sourceDirectory)) {
            $this->error('Path does not exists');

            return 2;
        }

        if (
            (! file_exists($outputDirectory) || ! $this->filesystem->isWritable($outputDirectory))
            && ! $this->filesystem->makeDirectory($outputDirectory, 493, true)
        ) {
            $this->error('Permissions error, cannot create a directory under the destination path');

            return 3;
        }

        $namespace = App::getNamespace();
        $namespace .= str_replace(DIRECTORY_SEPARATOR, "\\", Str::replaceFirst(App::path('/'), '', $sourceDirectory));
        
        $dataTransferObjects = Collection::make((new Finder)->files()->in($sourceDirectory))
            ->map(fn ($file) =>  implode("\\", [$namespace, $file->getBasename('.php')]))
            ->sort()
            ->filter(fn (string $className) => class_exists($className) && is_a($className, DataTransferObject::class, true))
            ->all();
        
        $generatedTypesCollection = Collection::make([]);

        foreach ($dataTransferObjects as $dataTransferObject) {
            (new TypeGenerator($dataTransferObject, $generatedTypesCollection))->generate();
        }

        $outputFilename .= $typesAsDeclarations ? '.d.ts' : '.ts';
        $outputFilePath = implode(DIRECTORY_SEPARATOR, [$outputDirectory, $outputFilename]);

        if (
            $this->filesystem->exists($outputFilePath)
            && ! $this->confirm('Are you sure you want to overwrite the output file?', $force)
        ) {
            return 0;
        }

        if (! $this->filesystem->put($outputFilePath, $generatedTypesCollection->join("\n\n"))) {
            $this->error('Something happened and types file could not be written');

            return 4;
        }

        $this->info("Types file successfully generated at \"{$outputFilePath}\"");

        return 0;
    }

    /**
     * Get command options with defaults following an order.
     */
    protected function getOptionsWithDefaults(): array
    {
        $options = array_merge(
            $this->options(),
            [
                'output' => static::OPTION_DEFAULT_OUTPUT,
                'source' => static::OPTION_DEFAULT_SOURCE,
                'filename' => static::OPTION_DEFAULT_FILENAME,
            ],
            array_filter(
                config('data-transfer-objects.types_generation', []),
                fn ($configValue) => $configValue !== null
            )
        );

        $options['output'] = resource_path($options['output']);
        $options['source'] = App::path($options['source']);

        return $options;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Force running replacing output files even if one exists'],
            ['output', 'o', InputOption::VALUE_OPTIONAL, 'Destination folder where to place generated types (must be relative to resources folder). Default: '.static::OPTION_DEFAULT_OUTPUT],
            ['source', 's', InputOption::VALUE_OPTIONAL, 'Source folder where to look at for data transfer objects (must be relative to app folder). Default: '.static::OPTION_DEFAULT_SOURCE],
            ['filename', null, InputOption::VALUE_OPTIONAL, 'Destination file name without exception for the types generated. Default: '.static::OPTION_DEFAULT_FILENAME],
            ['declarations', 'd', InputOption::VALUE_NONE, 'Generate types file as declarations (for e.g. types.d.ts instead of types.ts)'],
        ];
    }
}
