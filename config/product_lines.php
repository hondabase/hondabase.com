<?php

/**
 * Presentation map for the Browser slide wizard's root product-line slide.
 * Keys: type (matches taxonomy_nodes.type), label, desc, coming_soon (optional bool).
 * Order here is the display order on the homepage.
 * Types listed as coming_soon have no taxonomy data yet; they render as greyed cards.
 * The 'engines' type is intentionally excluded — it is a cross-cutting subject accessible
 * via search and engine facets, not a product line users browse from the root.
 */
return [
    'lines' => [
        ['type' => 'cars',         'label' => 'Cars',          'desc' => 'Automobiles & light trucks'],
        ['type' => 'motorcycles',  'label' => 'Motorcycles',   'desc' => 'Road, off-road & sport bikes'],
        ['type' => 'aircraft',     'label' => 'Aircraft',      'desc' => 'HondaJet & turbofan engines'],
        ['type' => 'marine',       'label' => 'Marine',        'desc' => 'Outboard motors & jet boats',       'coming_soon' => true],
        ['type' => 'power-equipment', 'label' => 'Power Equipment', 'desc' => 'Generators, tillers & mowers', 'coming_soon' => true],
        ['type' => 'atv',          'label' => 'ATVs',          'desc' => 'All-terrain vehicles',              'coming_soon' => true],
        ['type' => 'side-by-side', 'label' => 'Side-by-Sides', 'desc' => 'Pioneer SxS series',               'coming_soon' => true],
    ],
];
