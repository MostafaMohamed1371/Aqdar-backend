<?php

namespace App\Core\Enums;

enum SegmentPreference: string
{
    case Any = 'any';
    case NonDistressed = 'non_distressed';
    case Distressed = 'distressed';
}
