<?php
require_once('/var/www/tracker.php');

$title = "Honda Error/Trouble Codes - hondabase.com";
$description = "Comprehensive list of Honda OBD0, OBD1 & OBD2 trouble codes. Includes code descriptions and potential causes to diagnose check engine light issues.";

$errors = json_decode(file_get_contents('error-codes.json'), true);

track_page_view(trim(explode('-', $title)[0]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= $title ?></title>
	<link rel="stylesheet" href="../../base-styles.css">

	<meta name="description" content="<?= $description; ?>">
	<meta name="keywords" content="Honda, trouble codes, error codes, OBD0, OBD1, OBD2, diagnostic, Malfunction Indicator Light, MIL, check engine light, ECU, Engine Control Unit">
	<meta name="author" content="hondabase.com">

	<meta property="og:title" content="<?= $title ?>">
	<meta property="og:description" content="<?= $description; ?>">
	<meta property="og:type" content="website">
	<meta property="og:url" content="https://www.hondabase.com/reference/error-codes/">
	
	<meta name="twitter:card" content="summary">
	<meta name="twitter:title" content="<?= $title ?>">
	<meta name="twitter:description" content="<?= $description; ?>">

	<style>
	legend {
		font-size: x-large;
		font-weight: bold;
		margin: 0 0 0.5rem 0;
		color: var(--secondary);
	}

	article a {
		text-decoration: underline dotted;
		text-decoration-thickness: 1px;
	}

	dl { margin-left: 1rem; }

	dd { margin: 0.5rem 0; }

	dt {
		font-weight: bold;
		color: #333;
	}
	</style>
</head>
<body>
	<header>
		<h1>Honda Trouble/Error Codes</h1>
		
		<p>Honda vehicles with <strong>OBD0</strong>, <strong>OBD1</strong>, or <strong>OBD2</strong> diagnostic systems use trouble codes (also called error codes) to identify and communicate various system issues. When reading these codes on any of these systems, <em>one long flash equals 10 and one short flash equals 1</em>. For example, a pattern of one long flash followed by two short flashes would indicate code 12.</p>
		<p>For vehicles equipped with <strong>OBD1</strong> or <strong>OBD2</strong>, these codes can be retrieved by "jumping" (short-circuiting) the service connector - a process where you temporarily connect two specific pins in the diagnostic port using a jumper wire or paper clip to complete an electrical circuit. This causes the <strong>Malfunction Indicator Light (MIL)</strong> to flash in the specific pattern. These standardized codes help mechanics and DIY enthusiasts diagnose problems in the vehicle's systems.</p>
		<p>On older vehicles with <strong>OBD0</strong> systems, the codes are displayed through a flashing LED located directly on the <strong>ECU (Engine Control Unit)</strong>, using the same flash pattern. Even if the Check Engine Light (CEL) is off, the ECU will still blink the LED to indicate stored trouble codes. This is because the ECU retains the stored codes, even when the CEL is not illuminated. The stored codes can be reset by disconnecting the battery, which will also reset the LED blinking.</p>

		<p>Below you'll find a comprehensive list of Honda trouble codes along with their descriptions and potential causes.</p>
	</header>
	<main>
		<details>
			<summary>How to Access the Service Connector</summary>
			<p>To access the service connector and retrieve trouble codes on your Honda vehicle, follow these steps:</p>
			<ol>
				<li>Locate the <strong>two-pin service connector</strong> (usually inside a green rubber protection alongside a 3-pin connector) under the dashboard on the passenger side, near the ECU.</li>
				<li>Using a <em>jumper wire or paper clip</em>, connect the two pins to complete the electrical circuit.</li>
				<li>Turn the ignition key to the <strong>first position (ON)</strong> without starting the engine.</li>
				<li>The <strong>Malfunction Indicator Light (MIL)</strong> will flash a specific pattern, indicating the trouble code(s).</li>
				<li><em>Count the long and short flashes</em> to determine the code(s).</li>
			</ol>
			<figure>
				<img src="scs-connector.png" alt="Honda SCS Service Check Connector" width="300">
				<figcaption><em>Note: For convenience, you can purchase a female SCS Service Check Connector from eBay or Amazon. This connector can be kept in your vehicle for easy access whenever you need to check error codes.</em></figcaption>
			</figure>
		</details>
		<?php foreach ($errors as $error): ?>
		<article>
			<legend>Code <?= implode('-', $error['code']) ?></legend>
			<dl>
				<dt><?= $error['function']['short'] ?></dt>
				<dd><?= $error['function']['long']['en'] ?></dd>
			</dl>
		</article>
		<?php endforeach; ?>
	</main>
	<footer>
		<a href="https://www.hondabase.com">Hondabase</a> - Community-Driven Honda Knowledgebase
	</footer>
<script>
document.querySelectorAll('legend').forEach(legend => {
    const text   = legend.textContent;
    const codeId = text.replace('Code ', '');
	
    legend.innerHTML = 'Code ' + codeId.split('-').map(code => 
        `<a href="#${codeId}" onclick="navigator.clipboard.writeText(window.location.origin + window.location.pathname + '#${codeId}'); alert('Link copied to clipboard!');">${code}</a>`
    ).join('-');
    
    if (window.location.hash.substring(1) === text.replace('Code ', '')) {
		legend.scrollIntoView({ behavior: 'smooth', block: 'center' });
        legend.closest('article').animate([
            { transform: 'scale(1)' },
            { transform: 'scale(1.05)' },
            { transform: 'scale(1)' }
        ], {
            duration: 2000,
            easing: 'ease-in-out'
        });
    }
});
</script>
</body>
</html>