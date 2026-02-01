<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Enums;

enum MessageParseMode: string
{
    case HTML = 'HTML';
    case Markdown = 'Markdown';
    case MarkdownV2 = 'MarkdownV2';
    case None = '';
}