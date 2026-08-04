import { copyFileSync, mkdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';

const manifestPath = 'public/build/manifest.json';
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const entry = manifest['resources/css/base.css'];

if (!entry?.file) {
    throw new Error('Missing resources/css/base.css in Vite manifest.');
}

const source = join('public/build', entry.file);
const destination = 'public/assets/base.css';

mkdirSync(dirname(destination), { recursive: true });
copyFileSync(source, destination);

console.log(`Published ${destination} from ${source}`);
