<?php

namespace Workbench\App\Enums;

enum PostStatus: string
{
    case Published = 'published';

    case Hidden = 'hidden';
}
