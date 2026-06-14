<?php

namespace App\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;

class AlertIconInline extends AbstractInline
{
    private string $type;

    public function __construct(string $type)
    {
        parent::__construct();
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
