/**
 * Minify frontend assets (JS + CSS) into .min.* siblings.
 * Run before every release that touched assets: `npm run minify`
 * The plugin loads the .min files automatically unless SCRIPT_DEBUG is on.
 */
import { build } from 'esbuild';
import { readdirSync } from 'fs';
import { join } from 'path';

const root = new URL('..', import.meta.url).pathname;

const jsDir = join(root, 'assets/js');
const jsFiles = readdirSync(jsDir).filter(f => f.endsWith('.js') && !f.endsWith('.min.js'));

for (const file of jsFiles) {
    await build({
        entryPoints: [join(jsDir, file)],
        outfile: join(jsDir, file.replace(/\.js$/, '.min.js')),
        minify: true,
        allowOverwrite: true,
        logLevel: 'error',
    });
}

await build({
    entryPoints: [join(root, 'assets/css/frontend.css')],
    outfile: join(root, 'assets/css/frontend.min.css'),
    minify: true,
    allowOverwrite: true,
    logLevel: 'error',
});

console.log(`Minified ${jsFiles.length} JS files + frontend.css`);
