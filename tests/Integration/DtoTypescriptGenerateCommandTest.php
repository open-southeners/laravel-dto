<?php

namespace OpenSoutheners\LaravelDto\Tests\Integration;

use Illuminate\Support\Facades\App;
use Orchestra\Testbench\Concerns\InteractsWithPublishedFiles;
use function Orchestra\Testbench\workbench_path;

class DtoTypescriptGenerateCommandTest extends TestCase
{
    use InteractsWithPublishedFiles;

    protected $files = [
        'resources/js/types.ts',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'data-transfer-objects.types_generation' => [
                'output' => 'js',
                // 'source' => 'tests/Fixtures',
                'filename' => 'types',
                'declarations' => false,
            ],
        ]);
    }

    public function testDtoTypescriptGeneratesTypescriptTypesFile()
    {
        App::shouldReceive('getNamespace')
            ->once()
            ->andReturn('Workbench\App');

        App::shouldReceive('path')
            ->once()
            ->withArgs(['DataTransferObjects'])
            ->andReturn(workbench_path('app/DataTransferObjects'));

        App::shouldReceive('path')
            ->once()
            ->withArgs(['/'])
            ->andReturn(workbench_path('app'));

        $command = $this->artisan('dto:typescript');

        $command->expectsConfirmation('Are you sure you want to generate types from your data transfer objects?', 'yes');

        $command->expectsOutput('Types file successfully generated at "'.resource_path('js/types.ts').'"');

        $exitCode = $command->run();

        $this->assertEquals(0, $exitCode);

        $this->assertFileContains([
            'export type UpdatePostData',
            'export type CreatePostData',
            'export type UpdatePostFormData',
            "export type UpdatePostWithDefaultData = {\n\tpost_id?: string;\n\t",
        ], 'resources/js/types.ts');
    }
}
