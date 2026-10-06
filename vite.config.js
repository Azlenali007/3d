import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';
import { spawn, execSync } from 'child_process';

let phpProcess = null;

function phpServerPlugin() {
  return {
    name: 'php-server-plugin',
    configureServer(server) {
      try {
        // Ensure MariaDB service is running
        execSync('/etc/init.d/mariadb status || /etc/init.d/mariadb start', { stdio: 'ignore' });
      } catch (e) {
        // ignore
      }

      // Check if PHP server on 8085 is already listening
      try {
        execSync('nc -z 127.0.0.1 8085 || curl -s http://127.0.0.1:8085/settings', { timeout: 1000, stdio: 'ignore' });
      } catch (e) {
        // Start PHP server
        phpProcess = spawn('php', ['-S', '127.0.0.1:8085', 'backend/router.php'], {
          cwd: process.cwd(),
          stdio: 'inherit'
        });

        process.on('exit', () => {
          if (phpProcess) phpProcess.kill();
        });
      }
    }
  };
}

export default defineConfig({
  plugins: [
    tailwindcss(),
    phpServerPlugin()
  ],
  resolve: {
    alias: {
      '@': path.resolve(process.cwd(), '.'),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8085',
        changeOrigin: true,
      },
    },
  },
});
