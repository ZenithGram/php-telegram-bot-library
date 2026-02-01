<?php
namespace ZenithGram\ZenithGram\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OnCommand
{
    /**
     * @param string|array $command Сама команда, например '/start', 'поехали'
     */
    public function __construct(
        public string|array $command
    ) {}
}
