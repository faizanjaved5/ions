/* eslint-disable no-console */
import fs from 'fs';
import path from 'path';

const root = process.cwd();
const outDir = path.join(root, 'dist-navbar');
const filesToCopy = [
    { src: path.join(root, 'public', 'ion-navbar.php'), dest: path.join(outDir, 'index.php') },
    { src: path.join(root, 'public', 'ion-navbar-embed.php'), dest: path.join(outDir, 'ion-navbar-embed.php') },
    { src: path.join(root, 'public', 'ion-sprite.svg'), dest: path.join(outDir, 'ion-sprite.svg') },
];

try {
    if (!fs.existsSync(outDir)) {
        fs.mkdirSync(outDir, { recursive: true });
    }
    for (const f of filesToCopy) {
        if (!fs.existsSync(f.src)) continue;
        fs.copyFileSync(f.src, f.dest);
        console.log(`Copied ${path.basename(f.src)} to ${path.relative(root, f.dest)}`);
    }
} catch (err) {
    console.error('Failed to copy PHP sample file:', err);
    process.exitCode = 1;
}


