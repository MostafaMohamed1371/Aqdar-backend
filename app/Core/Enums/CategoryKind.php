<?php

namespace App\Core\Enums;

enum CategoryKind: string
{
    case Segment = 'segment';
    case DistressedChild = 'distressed_child';
    case Topic = 'topic';
}
