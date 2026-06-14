<?php

namespace App\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Query;

class GithubAlertProcessor
{
    private const TYPES = ['note', 'tip', 'important', 'warning', 'caution'];

    public function __invoke(DocumentParsedEvent $event): void
    {
        $query = (new Query)->where(Query::type(BlockQuote::class));

        foreach ($query->findAll($event->getDocument()) as $quote) {
            $paragraph = $quote->firstChild();
            $marker = $paragraph?->firstChild();

            if (! $paragraph instanceof Paragraph || ! $marker instanceof Text) {
                continue;
            }

            if (! preg_match('/^\[!('.implode('|', self::TYPES).')\]\s*$/i', $marker->getLiteral(), $match)) {
                continue;
            }

            $type = strtolower($match[1]);
            $next = $marker->next();
            $marker->detach();
            if ($next instanceof Newline) {
                $next->detach();
            }
            if (! $paragraph->hasChildren()) {
                $paragraph->detach();
            }

            $title = new Paragraph;
            $title->data->set('attributes/class', 'markdown-alert-title');
            $title->appendChild(new Text(ucfirst($type)));
            $quote->prependChild($title);

            $quote->data->set('attributes/class', "markdown-alert markdown-alert-{$type}");
            $quote->data->set('attributes/role', 'note');
            $quote->data->set('attributes/aria-label', ucfirst($type));
        }
    }
}
