<?php
require 'vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$yaml = "summary: 'Peak And Hold Injectors aka Low Impedance Injectors. Impedance typically ~2-5 ohms. At 12 volts, this is a draw of ~6-2,4A.'";

$parsed = Yaml::parse($yaml);
echo "Parsed: " . $parsed['summary'] . "\n";
