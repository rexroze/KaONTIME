import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const children = [];
let stopping = false;

function stop(code = 0) {
  if (stopping) return;
  stopping = true;
  for (const child of children) child.kill('SIGTERM');
  process.exitCode = code;
}

for (const [command, args, directory] of [
  ['php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000'], 'backend'],
  [process.platform === 'win32' ? 'npm.cmd' : 'npm', ['run', 'dev'], 'VueActivity'],
]) {
  const child = spawn(command, args, { cwd: `${root}${directory}`, stdio: 'inherit' });
  children.push(child);
  child.on('error', (error) => {
    console.error(`Unable to start ${command}: ${error.message}. See README.md for setup.`);
    stop(1);
  });
  child.on('exit', (code) => {
    if (!stopping) stop(code ?? 1);
  });
}

process.on('SIGINT', () => stop());
process.on('SIGTERM', () => stop());
