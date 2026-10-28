<?php
// Maintenance commands must never expose uploaded documents over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Extract text from the first .docx found in uploads/
$dir = __DIR__ . '/../uploads';
$files = glob($dir . '/*.docx');
if (empty($files)) {
    echo "No .docx files found in uploads/\n";
    exit(1);
}
$path = $files[0];
echo "Reading: " . basename($path) . "\n\n";
$zip = new ZipArchive();
if ($zip->open($path) === true) {
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) { echo "document.xml not found inside .docx\n"; exit(1); }

    // Normalize paragraphs to newlines
    $xml = preg_replace('/<w:p[^>]*>/', "\n", $xml);
    // Replace w:tab with tab
    $xml = str_replace(['<w:tab/>','<w:tab />'], "\t", $xml);
    // Strip tags
    $text = strip_tags($xml);
    // Collapse multiple blank lines
    $text = preg_replace("/\n{3,}/","\n\n", $text);
    // Trim
    $text = trim($text);

    echo $text . "\n";
} else {
    echo "Failed to open .docx file: " . basename($path) . "\n";
    exit(1);
}
