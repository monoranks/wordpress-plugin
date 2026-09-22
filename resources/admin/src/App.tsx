import { adminSettings } from '@/settings';
import { Overview } from '@/screens/Overview';
import { Settings } from '@/screens/Settings';

export function App() {
  return adminSettings().screen === 'settings' ? <Settings /> : <Overview />;
}
