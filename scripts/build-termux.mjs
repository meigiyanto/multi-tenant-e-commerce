import { existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const required = [
    ['esbuild Android ARM32', 'node_modules/@esbuild/android-arm'],
    ['Rollup Android ARM EABI', 'node_modules/@rollup/rollup-android-arm-eabi'],
    ['Tailwind Oxide WASI fallback', 'node_modules/@tailwindcss/oxide-wasm32-wasi'],
];

if (process.platform !== 'android' || process.arch !== 'arm') {
    console.error(
        `This command targets Termux Android ARM32. Detected: ${process.platform}/${process.arch}.`,
    );
    process.exit(1);
}

if (Number(process.versions.node.split('.')[0]) < 20) {
    console.error(`Vite 7 requires a newer Node.js runtime. Detected: ${process.version}.`);
    process.exit(1);
}

const missing = required.filter(([, path]) => !existsSync(path));

if (missing.length > 0) {
    console.error('Missing platform packages:');
    for (const [name] of missing) console.error(`- ${name}`);
    console.error('\nInstall the locked optional dependencies with:');
    console.error('npm install --include=optional');
    process.exit(1);
}

console.log('Termux Android ARM32 toolchain packages detected:');
for (const [name] of required) console.log(`- ${name}`);
console.log('\nStarting the production frontend build...');

const result = spawnSync('npm', ['run', 'build'], { stdio: 'inherit' });
process.exit(result.status ?? 1);
