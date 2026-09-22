import { useScreen } from '@/lib/screen';
import { Overview } from '@/screens/Overview';
import { Settings } from '@/screens/Settings';

/** One app for both menu entries; the screen follows the URL's page parameter (see lib/screen.ts). */
export function App() {
  const [screen, go] = useScreen();
  return screen === 'settings' ? <Settings go={go} /> : <Overview go={go} />;
}
