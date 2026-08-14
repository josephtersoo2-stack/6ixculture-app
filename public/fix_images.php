<?php
$publicFolder = __DIR__;
$linkFolder = $publicFolder . '/storage';

// Search for the storage folder based on common cPanel structures
$possibleTargets = [
    realpath($publicFolder . '/../storage/app/public'),
    realpath($publicFolder . '/../../storage/app/public'),
    realpath($publicFolder . '/../shopkingcpanel/storage/app/public'),
    realpath($publicFolder . '/../6ixculture/storage/app/public'),
    realpath($publicFolder . '/storage/app/public'),
];

$targetFolder = false;
foreach ($possibleTargets as $target) {
    if ($target && file_exists($target) && is_dir($target)) {
        $targetFolder = $target;
        break;
    }
}

if (!$targetFolder) {
    die("Could not find the target storage directory.");
}

// Fix permissions recursively so Apache web server can read all files and folders
function fixPermissions($path) {
    if (is_dir($path)) {
        chmod($path, 0755);
        $objects = scandir($path);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                fixPermissions($path . "/" . $object);
            }
        }
    } else if (is_file($path)) {
        chmod($path, 0644);
    }
}

fixPermissions($targetFolder);

// Remove the existing link/folder if it's broken
if (file_exists($linkFolder) || is_link($linkFolder)) {
    if (is_link($linkFolder)) {
        unlink($linkFolder);
    }
}

if (symlink($targetFolder, $linkFolder)) {
    echo "<h2>Success!</h2>";
    echo "<p>Permissions fixed to 0755/0644 and symlink created successfully pointing to: <b>" . $targetFolder . "</b></p>";
    echo "<p>Try loading your image link again now!</p>";
} else {
    echo "<h2>Failed!</h2> <p>Symlink creation failed.</p>";
}
