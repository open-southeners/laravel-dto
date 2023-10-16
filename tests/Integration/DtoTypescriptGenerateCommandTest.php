<?php

namespace OpenSoutheners\LaravelDto\Tests\Integration;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Mockery;
use Orchestra\Testbench\Concerns\InteractsWithPublishedFiles;

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
                'source' => 'tests/Fixtures',
                'filename' => 'types',
                'declarations' => false,
            ]
        ]);
    }

    public function testDtoTypescriptGeneratesTypescriptTypesFile()
    {
        App::shouldReceive('getNamespace')->once()->andReturn('OpenSoutheners\LaravelDto\Tests');
        App::shouldReceive('path')->once()->withArgs(['tests/Fixtures'])->andReturn(str_replace('/Integration', '/Fixtures', __DIR__));
        App::shouldReceive('path')->once()->withArgs(['/'])->andReturn(str_replace('/Integration', '', __DIR__));

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
