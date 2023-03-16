<?php

namespace OpenSoutheners\LaravelDto\Tests\Unit;

use OpenSoutheners\LaravelDto\Tests\Fixtures\CreatePostData;
use OpenSoutheners\LaravelDto\Tests\Fixtures\PostStatus;
use PHPUnit\Framework\TestCase;

class DataTransferObjectTest extends TestCase
{
    public function testDataTransferObjectFromArray()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => 'foo,bar,test',
            'status' => PostStatus::Published->value,
        ]);

        $this->assertTrue($data->status instanceof PostStatus);
        $this->assertEquals('Hello world', $data->title);
        $this->assertIsArray($data->tags);
        $this->assertContains('bar', $data->tags);
        $this->assertNull($data->post);
    }

    public function testDataTransferObjectFilled()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => 'foo,bar,test',
            'status' => PostStatus::Published->value,
        ]);

        $this->assertTrue($data->filled('status'));
        $this->assertFalse($data->filled('post'));
    }
}
