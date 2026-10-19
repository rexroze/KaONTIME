import { fileURLToPath } from 'node:url';

process.env.SCREENSHOT_DIR ||= fileURLToPath(new URL('../submission/screenshots/current/', import.meta.url));
await import('../VueActivity/scripts/verify-ui.mjs');
