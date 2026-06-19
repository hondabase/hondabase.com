<?php
require 'vendor/autoload.php';
use App\Models\Article;
use App\Support\ArticleDocument;

// This script will iterate through articles with suboptimal summaries
// and update them with better, SEO-friendly ones.
// Given the complexity of natural language generation, I will define a map of
// slugs to new summaries for the identified batch.

$newSummaries = [
    '6260a-testing' => 'Technical analysis of the Honda 6260A ECU test code, explaining how it triggers the diagnostic LED to flash engine error codes.',
    'add-knock-to-p30g00' => 'Step-by-step hardware guide for adding a knock sensor circuit to non-equipped Honda P30 OBD1 ECUs using specific component modifications.',
    'label-decode' => 'Learn how to interpret the identification labels on Honda OBD1 ECUs to determine their origin, engine compatibility, and transmission type.',
    'obd0-code-compatibility' => 'A guide to OBD0 Honda ECU code compatibility. Learn which ECUs can share code and how to successfully interchange binaries between units.',
    'p2p' => 'Technical overview of the P2P OBD2 Honda Civic EX ECU, covering stock bin configurations, ignition timing maps, and hardware specifications.',
    'popol-vuh' => 'A historical overview of the PGMFI.org Wiki project, covering its origins, development, and community contributions to Honda ECU tuning.',
    'super-pro-z' => 'Technical overview of the Xeltek SuperProZ EPROM programmer, including supported chip types and its use in Honda ECU chipping.',
    'text-formatting-rules' => 'Reference guide for Wiki markup formatting, including emphasis, lists, and term definitions used for editing Hondabase technical documentation.',
    'what-you-need' => 'Practical introduction to Honda ECU chipping: what it means, the essential tools required, and the process of burning new bin files.',
    'chipping-obd1-big-case' => 'Coming soon: A detailed guide for socketing and chipping large-case Honda OBD1 ECUs, including component requirements and process steps.',
    'new-markup-test-page' => 'A testing sandbox for experimenting with and verifying new Markdown and Wiki markup formatting features for Hondabase documentation.',
    'vise-grip' => 'A quick look at essential mechanical tools, such as the vise-grip, that are indispensable for hardware modifications on Honda vehicles.',
    'wiki' => 'General overview of the PGMFI.org Wiki project, documenting OBD0 and OBD1 Honda engine management systems.',
    'injector-sizing' => 'Learn how to correctly calculate and select the appropriate fuel injectors based on your target horsepower and engine modifications.',
    'obd1p08-auto-manual' => 'Technical guide for converting automatic Honda P08 OBD1 ECUs to manual transmission configurations by modifying internal resistor board values.',
    'p27' => 'Technical specifications and application overview for the P27 92-95 OBD1 Euro/Asian Civic ECU.',
    '02d011f0-1500' => 'Hardware modification guide for converting non-VTEC "11F0" Honda ECU boards to VTEC, including necessary component additions.',
    'p2e' => 'Technical specifications and application overview for the 96+ Honda Civic LX ECU.',
    'p2n' => 'Technical specifications and application overview for the 96+ Honda Civic HX ECU.',
    'p2t' => 'Technical specifications and application overview for the 99+ Honda Civic Si ECU.',
    'cpu' => 'Overview of the Central Processing Unit (CPU) role as the primary controller in Honda ECU engine management systems.',
    'd1780' => 'Technical datasheet and specifications for the NEC 2SD1780 transistor used in Honda ECU hardware circuits.',
    'g-spot' => 'Information on the community GSpot forum, dedicated to Honda ECU hardware analysis and technical discussions.',
    'most-popular' => 'Top-viewed articles in the Hondabase knowledgebase, providing easy access to popular tuning and diagnostics topics.',
    'octal' => 'Explanation of octal numbering and its relevance in digital logic and bitwise operations for Honda ECU tuning.',
    'oz-dm' => 'Overview of the Australian Domestic Market (OZaDM) Honda vehicle and ECU specifications.',
    'pj7' => 'Technical specifications and application overview for the 86-87 Honda Prelude Si (B20A3) ECU.',
    'square-brackets' => 'Technical issue report regarding formatting errors in wiki pages requiring maintenance and content correction.',
    'wot' => 'Definition and significance of "Wide Open Throttle" (WOT) in Honda engine tuning and diagnostic mapping.',
    '5128xram' => 'Technical explanation of the 2K byte 5128 SRAM chip used in Honda OBD0 ECUs, including its memory mapping and purpose.',
    '66k-resources' => 'Comprehensive resource list for the Oki 66K processor family used in Honda OBD1 and OBD2 ECUs, including assemblers and manuals.',
    'blue-loctite' => 'Guide to the appropriate use of Loctite thread-locking compounds for securing engine and hardware components in Honda projects.',
    'disable-vtec-vss-check-p28' => 'Technical guide for bypassing the Vehicle Speed Sensor (VSS) check in the VTEC routine for P28 Honda OBD1 ECUs.',
    'dual-maps' => 'Introduction to dual-map ROM setups in Honda ECUs, allowing for switchable engine tuning profiles.',
    'dual-tables' => 'Explanation of dual-table ROM structures in Honda ECUs, enabling advanced tuning features like VTEC-dependent fuel and ignition maps.',
    'ecu-boost-controller' => 'Overview of developmental Honda ECU boost control features, utilizing PWM signals for wastegate solenoid pressure regulation.',
    'ideal-gas-law' => 'Application of the Ideal Gas Law in Honda speed-density engine management systems for calculating fuel requirements based on pressure and temperature.',
    'inter-wiki' => 'Reference for inter-wiki linking capabilities in Hondabase, allowing seamless navigation between related technical documentation projects.',
    'internal-rom' => 'Explanation of Internal ROM memory storage within Honda ECU microcontrollers (MCUs) and its role in engine management.',
    'java-script' => 'Overview of JavaScript scripting for automating features in Crome and other Honda ROM editor software.',
    'launch-control' => 'Technical explanation of launch control "two-step" rev limiter features in Honda ECUs, aiding in consistent vehicle standing starts.',
    'obd0-edit' => 'Information on the community OBD0Edit forum, a resource for technical discussions and development regarding OBD0 Honda ECUs.',
    'obd1-8bit-fuel' => 'Technical formula reference for interpreting 8-bit fuel values in ROM editor tables for Honda OBD1 engine management.',
    'p1j' => 'Overview of the 96-00 UK Honda Civic D14 ECU, covering its compatibility and VTEC conversion potential.',
    'p1k' => 'Overview of the 96-00 UK Honda Civic D14 ECU, covering its compatibility and VTEC conversion potential.',
    'red-loctite' => 'Technical advice on the use and removal of Red Loctite thread-locking compound in high-stress Honda engine component applications.',
    'turbo-compressor-map' => 'Guide to reading and interpreting turbocharger compressor maps for matching turbochargers to Honda engine performance goals.',
    'uv-erase' => 'Technical guide to using UV light for erasing EPROM chips with transparent windows for re-programming.',
    'warning-about-adding-a-knock-sensor' => 'Technical warning regarding knock sensor installation on non-equipped engines, citing cylinder bore and knock board calibration complexities.',
];

$updatedCount = 0;

foreach ($newSummaries as $slug => $summary) {
    $article = Article::where('slug', $slug)->first();
    if (!$article) continue;
    
    $repoPath = 'content/' . $article->repo_path;
    if (!file_exists($repoPath)) continue;
    
    $raw = file_get_contents($repoPath);
    $doc = ArticleDocument::parse($raw);
    
    $doc['fm']['summary'] = $summary;
    $newRaw = ArticleDocument::compose($doc['fm'], $doc['body']);
    
    file_put_contents($repoPath, $newRaw);
    
    // Update DB
    $article->summary = $summary;
    $article->save();
    $updatedCount++;
}

echo "Successfully updated $updatedCount articles with SEO-friendly summaries.\n";
