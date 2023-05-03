<?php

namespace App\Enums;

enum JobStatus: string
{
    case COMPLETED = 'completed';
    case RELEASED = 'released';
}
