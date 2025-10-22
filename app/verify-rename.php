<?php
/**
 * Quick verification that all file renames and references are correct
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Rename Verification</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .check { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .pass { background: #d1fae5; border-left: 4px solid #10b981; }
        .fail { background: #fee2e2; border-left: 4px solid #ef4444; }
        .info { background: #e0f2fe; border-left: 4px solid #3b82f6; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
        .summary { font-size: 1.2em; font-weight: bold; margin: 20px 0; padding: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 File Rename Verification</h1>
        <p><strong>Date:</strong> <?= date('Y-m-d H:i:s') ?></p>

        <?php
        $passed = 0;
        $failed = 0;
        $issues = [];

        // Check 1: Verify renamed files exist
        echo '<h2>1️⃣ Renamed Files Exist</h2>';
        $renamedFiles = [
            'ion-uploader.php',
            'ion-uploader.css',
            'ion-uploader.js',
            'ion-uploaderpro.js',
            'ion-uploadermultipart.php',
            'ion-uploaderbackground.js'
        ];

        foreach ($renamedFiles as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                $size = filesize($path);
                $mtime = date('Y-m-d H:i:s', filemtime($path));
                echo "<div class='check pass'>✅ <code>$file</code> exists (" . number_format($size) . " bytes, modified: $mtime)</div>";
                $passed++;
            } else {
                echo "<div class='check fail'>❌ <code>$file</code> NOT FOUND</div>";
                $failed++;
                $issues[] = "$file is missing";
            }
        }

        // Check 2: Verify old files don't exist (or are backups)
        echo '<h2>2️⃣ Old Files Check</h2>';
        $oldFiles = [
            'ionuploader.php',
            'ionuploader.css',
            'ionuploader.js',
            'ionuploaderpro2.js',
            'ionuploadermultipart3.php',
            'ionuploaderbackground.js'
        ];

        foreach ($oldFiles as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                echo "<div class='check info'>ℹ️ <code>$file</code> still exists (may be used elsewhere or as backup)</div>";
            } else {
                echo "<div class='check pass'>✅ <code>$file</code> removed (clean)</div>";
                $passed++;
            }
        }

        // Check 3: Verify ion-uploader.php has correct references
        echo '<h2>3️⃣ ion-uploader.php References</h2>';
        $uploaderPhp = file_get_contents(__DIR__ . '/ion-uploader.php');
        
        $checks = [
            'ion-uploader.css' => 'CSS file reference',
            'ion-uploader.js' => 'Core JS file reference',
            'ion-uploaderpro.js' => 'Pro JS file reference'
        ];

        foreach ($checks as $search => $desc) {
            if (strpos($uploaderPhp, $search) !== false) {
                echo "<div class='check pass'>✅ References <code>$search</code> - $desc</div>";
                $passed++;
            } else {
                echo "<div class='check fail'>❌ Missing reference to <code>$search</code> - $desc</div>";
                $failed++;
                $issues[] = "ion-uploader.php missing $search reference";
            }
        }

        // Check 4: Verify JS files have correct endpoint
        echo '<h2>4️⃣ JavaScript Backend Endpoint</h2>';
        
        $jsFiles = [
            'ion-uploader.js',
            'ion-uploaderpro.js'
        ];

        foreach ($jsFiles as $file) {
            $content = file_get_contents(__DIR__ . '/' . $file);
            if (strpos($content, 'ion-uploadermultipart.php') !== false) {
                echo "<div class='check pass'>✅ <code>$file</code> references <code>ion-uploadermultipart.php</code></div>";
                $passed++;
            } else {
                echo "<div class='check fail'>❌ <code>$file</code> does NOT reference <code>ion-uploadermultipart.php</code></div>";
                $failed++;
                $issues[] = "$file has wrong endpoint reference";
            }
        }

        // Check 5: Verify creators.php references
        echo '<h2>5️⃣ creators.php References</h2>';
        $creatorsPhp = file_get_contents(__DIR__ . '/creators.php');
        
        if (strpos($creatorsPhp, 'ion-uploader.php') !== false) {
            $count = substr_count($creatorsPhp, 'ion-uploader.php');
            echo "<div class='check pass'>✅ <code>creators.php</code> references <code>ion-uploader.php</code> ($count times)</div>";
            $passed++;
        } else {
            echo "<div class='check fail'>❌ <code>creators.php</code> does NOT reference <code>ion-uploader.php</code></div>";
            $failed++;
            $issues[] = "creators.php still references old filename";
        }

        // Check 6: Verify diagnostic files
        echo '<h2>6️⃣ Diagnostic Files Updated</h2>';
        
        $diagnosticFiles = [
            'check-multipart-version.php' => 'ion-uploadermultipart.php',
            'check-upload.php' => 'ion-uploaderpro.js',
            'upload-diagnostics.php' => 'ion-uploaderpro.js',
            'fix-upload-system.php' => 'ion-uploaderpro.js'
        ];

        foreach ($diagnosticFiles as $file => $expectedRef) {
            $content = file_get_contents(__DIR__ . '/' . $file);
            if (strpos($content, $expectedRef) !== false) {
                echo "<div class='check pass'>✅ <code>$file</code> references <code>$expectedRef</code></div>";
                $passed++;
            } else {
                echo "<div class='check fail'>❌ <code>$file</code> does NOT reference <code>$expectedRef</code></div>";
                $failed++;
                $issues[] = "$file needs updating";
            }
        }

        // Summary
        echo '<h2>📊 Summary</h2>';
        $total = $passed + $failed;
        $passRate = $total > 0 ? round(($passed / $total) * 100) : 0;
        
        if ($failed === 0) {
            echo "<div class='summary' style='background: #d1fae5; color: #065f46;'>";
            echo "✅ ALL CHECKS PASSED ($passed/$total)<br>";
            echo "All files have been successfully renamed and all references updated!";
            echo "</div>";
        } else {
            echo "<div class='summary' style='background: #fee2e2; color: #991b1b;'>";
            echo "❌ ISSUES FOUND: $failed failures, $passed passed ($passRate% success rate)<br>";
            echo "<strong>Issues:</strong><ul>";
            foreach ($issues as $issue) {
                echo "<li>$issue</li>";
            }
            echo "</ul></div>";
        }

        // Next steps
        echo '<h2>📋 Next Steps</h2>';
        if ($failed === 0) {
            echo '<div class="check pass">';
            echo '<strong>Ready to Deploy!</strong><br>';
            echo '1. Upload all 6 renamed core files to server<br>';
            echo '2. Upload all updated diagnostic files to server<br>';
            echo '3. Upload creators.php to server<br>';
            echo '4. Clear browser cache and PHP opcache<br>';
            echo '5. Run check-multipart-version.php to verify<br>';
            echo '6. Test upload with a small video file';
            echo '</div>';
        } else {
            echo '<div class="check fail">';
            echo '<strong>Fix Issues First!</strong><br>';
            echo 'Review and resolve the issues listed above before deploying to production.';
            echo '</div>';
        }
        ?>

        <p style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 0.9em;">
            <strong>Verification Script:</strong> verify-rename.php<br>
            <strong>Documentation:</strong> FILE_RENAME_COMPLETE.md
        </p>
    </div>
</body>
</html>

