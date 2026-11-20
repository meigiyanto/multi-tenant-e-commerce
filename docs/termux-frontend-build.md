# Building the frontend on Termux (Android ARM32)

The project lockfile already includes the Android ARM32 esbuild and Rollup
packages. Tailwind CSS 4.3.5 includes its WASI Oxide fallback, which is
intended for platforms without a matching native binding and includes fixes
for loading in Android sandboxed environments.

Use the local Termux toolchain instead of downloading the GitHub Actions
frontend artifact:

```sh
cd ~/multi-tenant-e-commerce
npm install --include=optional
npm run build:termux
```

The first command installs dependencies selected for the current Android
ARM32 runtime while respecting `package-lock.json`. It does not require
deleting `node_modules` or regenerating the lockfile.

The second command verifies the expected platform packages are present and
runs the existing Vite production build. The generated assets are written
directly to `public/build/`, which Laravel serves through its Vite manifest.

Requirements:
- Termux on Android ARM32 (`process.platform === "android"`,
  `process.arch === "arm"`).
- Node.js 20.19+; the project's current Node.js 24 runtime is suitable.

If the build fails, share the complete output from `npm run build:termux`.
Do not replace the lockfile or downgrade Vite/Tailwind pre-emptively.
