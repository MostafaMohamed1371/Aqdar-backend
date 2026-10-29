<?php

namespace App\Core\Enums;

enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Push = 'push';
}
