<?php

namespace OpenSoutheners\LaravelDto\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:dto')]
class DtoMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:dto';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new data transfer object';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'DataTransferObject';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        $stubSuffix = '';
        $requestOption = $this->option('request');

        if (! is_null($requestOption)) {
            $stubSuffix .= '.request';
        }

        if ($requestOption === true) {
            $stubSuffix .= '.plain';
        }

        $stub = "/stubs/dto{$stubSuffix}.stub";

        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\DataTransferObjects';
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $requestOption = $this->option('request');

        if ($requestOption === true) {
            return $stub;
        }

        return $this->replaceProperties($stub, $requestOption)
            ->replaceRequestClass($stub, $requestOption);
    }

    /**
     * Replace the namespace for the given stub.
     *
     * @param  string  $stub
     * @param  string|null  $requestClass
     * @return $this
     */
    protected function replaceProperties(&$stub, $requestClass)
    {
        $searches = [
            '{{ properties }}',
            '{{properties}}',
        ];

        $properties = $requestClass ? $this->getProperties($requestClass) : '// ';

        foreach ($searches as $search) {
            $stub = str_replace($search, $properties, $stub);
        }

        return $this;
    }

    /**
     * Get the request properties for the given class.
     *
     * @return string
     */
    protected function getProperties(string $requestClass)
    {
        $requestInstance = new $requestClass();
        $properties = '';

        $requestRules = $requestInstance->rules();
        $firstRequestRuleProperty = array_key_first($requestRules);

        // TODO: Sort nulls here to be prepended (need to create array first)
        foreach ($requestRules as $property => $rules) {
            if (str_contains($property, '.')) {
                continue;
            }

            $originalPropertyName = $property;

            if (str_ends_with($property, '_id')) {
                $property = preg_replace('/_id$/', '', $property);
            }

            $property = Str::camel($property);

            $rules = implode('|', is_array($rules) ? $rules : [$rules]);

            $propertyType = match (true) {
                str_contains($rules, 'string') => 'string',
                str_contains($rules, 'numeric') => 'int',
                str_contains($rules, 'integer') => 'int',
                str_contains($rules, 'array') => 'array',
                str_contains($rules, 'json') => '\stdClass',
                str_contains($rules, 'date') => '\Illuminate\Support\Carbon',
                default => 'string',
            };

            if (str_contains($rules, 'nullable')) {
                $propertyType = "?{$propertyType}";
            }

            if ($firstRequestRuleProperty !== $originalPropertyName) {
                $properties .= ",\n\t\t";
            }

            $properties .= "public {$propertyType} \${$property}";

            if (str_contains($rules, 'nullable')) {
                $properties .= ' = null';
            }
        }

        return $properties;
    }

    /**
     * Replace the request class for the given stub.
     *
     * @param  string  $stub
     * @param  string|null  $requestClass
     * @return string
     */
    public function replaceRequestClass(&$stub, $requestClass)
    {
        $returnRequestClass = '// ';

        if ($requestClass && class_exists($requestClass)) {
            $returnRequestClass = 'return ';
            $returnRequestClass .= (new \ReflectionClass($requestClass))->getShortName();
            $returnRequestClass .= '::class;';
        }

        $searches = [
            '{{ requestClass }}' => $requestClass,
            '{{requestClass}}' => $requestClass,
            '{{ returnRequestClass }}' => $returnRequestClass,
            '{{returnRequestClass}}' => $returnRequestClass,
        ];

        foreach ($searches as $search => $replace) {
            $stub = str_replace($search, $replace, $stub);
        }

        return $stub;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the data transfer object already exists'],
            ['request', 'r', InputOption::VALUE_OPTIONAL, 'Create the class implementing ValidatedDataTransferObject interface & request method'],
        ];
    }
}
