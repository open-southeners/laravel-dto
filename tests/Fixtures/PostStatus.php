<?php

namespace OpenSoutheners\LaravelDto\Tests\Fixtures;

enum PostStatus: string
{
    case Published = 'published';

    case Hidden = 'hidden';
}
