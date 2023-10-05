<?php

namespace OpenSoutheners\LaravelDto\Tests\Integration;

use Orchestra\Testbench\Concerns\InteractsWithPublishedFiles;

class DtoMakeCommandTest extends TestCase
{
    use InteractsWithPublishedFiles;

    protected $files = [
        'app/DataTransferObjects/CreatePostData.php',
    ];

    public function testMakeDataTransferObjectCommandCreatesBasicClassWithName()
    {
        $this->artisan('make:dto', ['name' => 'CreatePostData'])
            ->assertExitCode(0);

        $this->assertFileContains([
            'namespace App\DataTransferObjects;',
            'final class CreatePostData',
        ], 'app/DataTransferObjects/CreatePostData.php');
    }

    public function testMakeDataTransferObjectCommandWithEmptyRequestOptionCreatesTheFileWithValidatedRequest()
    {
        $this->artisan('make:dto', [
            'name' => 'CreatePostData',
            '--request' => true,
        ])->assertExitCode(0);

        $this->assertFileContains([
            'namespace App\DataTransferObjects;',
            'final class CreatePostData',
            'implements ValidatedDataTransferObject',
            'public static function request(): string',
        ], 'app/DataTransferObjects/CreatePostData.php');
    }

    // TODO: Test properties from rules population
    public function testMakeDataTransferObjectCommandWithRequestOptionCreatesTheFileWithProperties()
    {
        $this->artisan('make:dto', [
            'name' => 'CreatePostData',
            '--request' => 'OpenSoutheners\LaravelDto\Tests\Fixtures\PostCreateFormRequest',
        ])->assertExitCode(0);

        $this->assertFileContains([
            'namespace App\DataTransferObjects;',
            'use OpenSoutheners\LaravelDto\Tests\Fixtures\PostCreateFormRequest;',
            'final class CreatePostData',
            'implements ValidatedDataTransferObject',
            'return PostCreateFormRequest::class;',
        ], 'app/DataTransferObjects/CreatePostData.php');
    }
}
