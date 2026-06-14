<?php
require '/var/www/hondabase/www/vendor/autoload.php';

use League\HTMLToMarkdown\HtmlConverter;

function html_table_to_md(string $tableHtml, array $ported): string
{
    // Load table HTML in DOMDocument
    $doc = new DOMDocument();
    // Use mb_convert_encoding to handle UTF-8 properly
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $tableHtml);
    
    $rows = $doc->getElementsByTagName('tr');
    if ($rows->length === 0) {
        return "";
    }
    
    $md = "";
    $isFirst = true;
    $colCount = 0;
    
    $conv = new HtmlConverter([
        'header_style' => 'atx',
        'strip_tags' => true,
        'remove_nodes' => 'script style',
        'hard_break' => true,
        'use_autolinks' => false
    ]);
    
    foreach ($rows as $tr) {
        $cells = [];
        $tds = $tr->childNodes;
        foreach ($tds as $td) {
            if ($td->nodeName === 'td' || $td->nodeName === 'th') {
                // Convert cell HTML to Markdown
                $cellHtml = '';
                foreach ($td->childNodes as $child) {
                    $cellHtml .= $td->ownerDocument->saveHTML($child);
                }
                
                // Delink internal wiki links inside the cell
                $cellHtml = preg_replace_callback('/href="\/pgmfi\/wiki\/library\/([^"\s]+)\s*"/', function($m) use ($ported) {
                    $slug = rtrim($m[1], '/');
                    return in_array($slug, $ported, true) ? "href=\"/cars/electronics/{$slug}\"" : '';
                }, $cellHtml);
                
                // Convert to markdown
                $cellMd = $conv->convert($cellHtml);
                
                // Replace internal links markdown format
                $cellMd = preg_replace_callback('/\[([^\]]+)\]\(\/pgmfi\/wiki\/library\/([^)\s]+)\s*\)/', function($m) use ($ported) {
                    $text = $m[1];
                    $slug = rtrim($m[2], '/');
                    return in_array($slug, $ported, true) ? "[{$text}](/cars/electronics/{$slug})" : $text;
                }, $cellMd);
                
                // Clean up newlines inside cells
                $cellMd = str_replace(["\r", "\n"], ' ', $cellMd);
                $cellMd = preg_replace('/\s+/', ' ', $cellMd);
                $cellMd = trim($cellMd);
                
                $cells[] = $cellMd;
            }
        }
        
        if (empty($cells)) {
            continue;
        }
        
        if ($isFirst) {
            $colCount = count($cells);
            $md .= "| " . implode(" | ", $cells) . " |\n";
            $md .= "| " . implode(" | ", array_fill(0, $colCount, ":---")) . " |\n";
            $isFirst = false;
        } else {
            // Pad cells if they are fewer than header columns
            while (count($cells) < $colCount) {
                $cells[] = "";
            }
            // Truncate if there are more
            $cells = array_slice($cells, 0, $colCount);
            
            $md .= "| " . implode(" | ", $cells) . " |\n";
        }
    }
    
    return "\n" . $md . "\n";
}

function convert_wiki_html(string $html, array $ported): string
{
    // Clean up carriage returns
    $html = str_replace('\\n', "\n", $html);
    $html = preg_replace('#<p>\s*</p>#i', '', $html);
    
    // Find all table tags, parse and replace them with Markdown tables
    $html = preg_replace_callback('/<table\b[^>]*>.*?<\/table>/is', function($m) use ($ported) {
        return html_table_to_md($m[0], $ported);
    }, $html);
    
    // Convert the remaining HTML
    $conv = new HtmlConverter([
        'header_style' => 'atx',
        'strip_tags' => true,
        'remove_nodes' => 'script style',
        'hard_break' => true,
        'use_autolinks' => false
    ]);
    
    $md = $conv->convert($html);
    
    // Repoint internal links
    $md = preg_replace_callback('/\[([^\]]+)\]\(\/pgmfi\/wiki\/library\/([^)\s]+)\s*\)/', function($m) use ($ported) {
        $text = $m[1];
        $slug = rtrim($m[2], '/');
        return in_array($slug, $ported, true) ? "[{$text}](/cars/electronics/{$slug})" : $text;
    }, $md);
    
    // Terminology casing
    $CASE = ['OBD0', 'OBD1', 'OBD2', 'OBD', 'VTEC', 'SOHC', 'DOHC', 'ECU', 'ECM', 'TPS', 'IAT',
             'ECT', 'EGR', 'ELD', 'LAF', 'VSS', 'IAC', 'EACV', 'TDC', 'CYP', 'CKP', 'CKF', 'TCU',
             'CEL', 'MIL', 'SCS', 'EGT'];
    foreach ($CASE as $t) {
        $md = preg_replace('/(?<![\w\/-])' . preg_quote($t, '/') . '(?![\w\/-])/i', $t, $md);
    }
    
    return trim(preg_replace("/\n{3,}/", "\n\n", $md)) . "\n";
}
