import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from '@/App';
import '@/index.css';

// Enqueued only on the plugin's own screens (src/Assets.php); a missing mount means the screen changed, so do nothing.
const mount = document.getElementById('monoranks-admin');
if (mount) {
  createRoot(mount).render(
    <StrictMode>
      <App />
    </StrictMode>,
  );
}
