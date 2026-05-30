<?php

namespace App\Enums;

enum EntryStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
