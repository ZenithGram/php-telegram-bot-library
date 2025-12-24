<?php

namespace ZenithGram\ZenithGram\Enums;

enum PaginationNumberStyle: int
{
    /**
     * 1, 2, 3, ...
     */
    case CLASSIC = 0;

    /**
     * 1️⃣, 2️⃣, 3️⃣, ...
     */
    case EMOJI = 1;
}