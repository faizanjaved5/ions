<?php

// CONFIGURATION
$inputFile         = "input.txt";
$templateDir       = "";  // Directory for templates (if any)
$outputDir         = "output/";
$logDir            = "logs/";
$fontFile          = __DIR__ . "/fonts/BebasNeue-Regular.ttf"; // Font file in /fonts/
$fontSize          = 22;
$textColorRGB      = [255, 255, 255]; // White text
$defaultTemplate   = "canvas.png";    // Default template image
$enableCompression = true;
$compressionLevel  = 6; // For PNG: 0 (none) to 9 (max)

// Ensure log directory exists
if (!file_exists($logDir)) mkdir($logDir, 0777, true);

// Set up logging
$logFile = $logDir . "generation_log.txt";
$logHandle = fopen($logFile, "w") or die("Cannot open log file.");

// Check font file presence
if (!file_exists($fontFile)) {
    fwrite($logHandle, "ERROR: Font file not found: $fontFile\n");
    die("Font file not found: $fontFile\n");
} else {
    fwrite($logHandle, "Font file found: $fontFile\n");
}

// Read input lines
$lines = file($inputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$lines) {
    fwrite($logHandle, "ERROR: No input found.\n");
    die("No input found.");
}

// Function to center text in a vertical box area
function centerTextInArea($image, $text, $fontFile, $fontSize, $color, $boxTop, $boxBottom) {
    $imgWidth = imagesx($image);
    
    $bbox = imagettfbbox($fontSize, 0, $fontFile, $text);
    if (!$bbox) return false;

    $textWidth = $bbox[2] - $bbox[0];
    $textHeight = $bbox[1] - $bbox[7];

    $x = intval(($imgWidth - $textWidth) / 2);
    // Vertically center text inside the box
    $boxHeight = $boxBottom - $boxTop;
    $y = intval($boxTop + ($boxHeight + $textHeight) / 2);

    return imagettftext($image, $fontSize, 0, $x, $y, $color, $fontFile, $text);
}

$counter = 1;
foreach ($lines as $lineNumber => $line) {
    $parts = array_map('trim', explode(',', $line));
    $text1 = $parts[0] ?? '';
    $text2 = $parts[1] ?? '';
    $templateImage = $parts[2] ?? '';

    if (!$text1) {
        fwrite($logHandle, "Line " . ($lineNumber + 1) . " skipped (no primary text).\n");
        continue;
    }

    $template = $templateImage ? $templateDir . $templateImage : $templateDir . $defaultTemplate;

    if (!file_exists($template)) {
        fwrite($logHandle, "Line " . ($lineNumber + 1) . " ERROR: Template not found - $template\n");
        continue;
    }

    // Load template image
    $image = null;
    $extension = strtolower(pathinfo($template, PATHINFO_EXTENSION));
    
    if ($extension === 'jpg' || $extension === 'jpeg') {
        $image = imagecreatefromjpeg($template);
    } elseif ($extension === 'png') {
        $image = imagecreatefrompng($template);
    }
    
    if (!$image) {
        fwrite($logHandle, "Line " . ($lineNumber + 1) . " ERROR: Could not load image - $template\n");
        continue;
    }
    
    // ✅ Force conversion to truecolor image (required for color allocation and text overlay)
    $trueColorImage = imagecreatetruecolor(imagesx($image), imagesy($image));
    
    // ✅ Preserve transparency for PNGs
    if ($extension === 'png') {
        imagealphablending($trueColorImage, false);
        imagesavealpha($trueColorImage, true);
        $transparent = imagecolorallocatealpha($trueColorImage, 0, 0, 0, 127);
        imagefill($trueColorImage, 0, 0, $transparent);
    }
    
    // Copy original to new truecolor canvas
    imagecopy($trueColorImage, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
    imagedestroy($image); // Destroy original
    
    $image = $trueColorImage;

    if (!$image) {
        fwrite($logHandle, "Line " . ($lineNumber + 1) . " ERROR: Could not load image - $template\n");
        continue;
    }

    // Allocate text color
    $textColor = imagecolorallocate($image, ...$textColorRGB);
    if ($textColor === false) {
        fwrite($logHandle, "ERROR: Failed to allocate text color.\n");
        imagedestroy($image);
        continue;
    }

    // Define vertical box area for text (adjust these values to fit your green area)
    $imgHeight = imagesy($image);
    $textBoxTop = intval($imgHeight * 0.55);   // approx 65% from top
    $textBoxBottom = $imgHeight - 40;          // some padding from bottom

    // Draw primary text centered inside the green box area, slightly higher
    $result1 = centerTextInArea($image, $text1, $fontFile, $fontSize, $textColor, $textBoxTop, $textBoxBottom - 30);
    if ($result1 === false) {
        fwrite($logHandle, "ERROR: imagettftext failed for primary text: '$text1'\n");
        imagedestroy($image);
        continue;
    }

    // Draw secondary text if present, below primary
    if (!empty($text2)) {
        $result2 = centerTextInArea($image, $text2, $fontFile, $fontSize, $textColor, $textBoxBottom - 30, $textBoxBottom);
        if ($result2 === false) {
            fwrite($logHandle, "ERROR: imagettftext failed for secondary text: '$text2'\n");
        }
    }

    // Sanitize text1 for filename use
    $rawName = strtolower(trim($text1));
    $cleanText = trim(preg_replace('/[^a-z0-9]+/', '-', $rawName), '-');
    $filename = "ion-" . ($cleanText ?: "image" . $counter);

    // Determine folder from first alphabetic character of original text
    preg_match('/[a-z]/i', $rawName, $matches);
    $letter = isset($matches[0]) ? strtolower($matches[0]) : 'z';

    // Create folder if necessary
    $folder = $outputDir . $letter;
    if (!file_exists($folder)) mkdir($folder, 0777, true);

    // Final image path (extension added later)
    $finalPath = "$folder/$filename";

    // Save image with proper format and compression
    $saveSuccess = false;
    if ($enableCompression && $extension === 'png') {
        $finalPath .= ".png";
        $saveSuccess = imagepng($image, $finalPath, $compressionLevel);
    } else {
        $finalPath .= ".jpg";
        $saveSuccess = imagejpeg($image, $finalPath, $enableCompression ? 80 : 100);
    }

    if ($saveSuccess) {
        fwrite($logHandle, "SUCCESS: $finalPath generated.\n");
    } else {
        fwrite($logHandle, "ERROR: Failed to save image for text '$text1'.\n");
    }

    imagedestroy($image);
    $counter++;
}

fwrite($logHandle, "DONE: Processed " . ($counter - 1) . " items.\n");
fclose($logHandle);

echo "Image generation complete. Log saved to $logFile\n";

?>
