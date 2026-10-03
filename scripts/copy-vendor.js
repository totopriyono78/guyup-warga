// Menyalin library JS/CSS ke public/ agar server tidak memerlukan Node.js.
import { copyFileSync, mkdirSync, readdirSync } from 'node:fs';

mkdirSync('public/js', { recursive: true });
copyFileSync('node_modules/alpinejs/dist/cdn.min.js', 'public/js/alpine.min.js');
copyFileSync('node_modules/qrcode-generator/qrcode.js', 'public/js/qrcode.js');

mkdirSync('public/vendor/leaflet/images', { recursive: true });
copyFileSync('node_modules/leaflet/dist/leaflet.js', 'public/vendor/leaflet/leaflet.js');
copyFileSync('node_modules/leaflet/dist/leaflet.css', 'public/vendor/leaflet/leaflet.css');
for (const f of readdirSync('node_modules/leaflet/dist/images')) {
    copyFileSync(`node_modules/leaflet/dist/images/${f}`, `public/vendor/leaflet/images/${f}`);
}
console.log('JS vendor disalin ke public/js dan public/vendor');
