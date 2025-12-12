<?php

namespace ZenithGram\ZenithGram;

enum MessageParseMode: string
{
    case HTML = 'HTML';
    case Markdown = 'Markdown';
    case MarkdownV2 = 'MarkdownV2';
    case None = '';
}