import { useColorScheme } from 'react-native';

export const BRAND = {
  gradientFrom: '#27253f',
  gradientTo:   '#605a6d',
  accent:       '#6a5cff',
};

export interface ThemeColors {
  bg: string;
  surface: string;
  text: string;
  textMuted: string;
  border: string;
  borderStrong: string;
  placeholder: string;
  primary: string;       // brand accent
  headerBg: string;      // nav header background
  headerText: string;
  tabActive: string;
  tabInactive: string;
  danger: string;
  success: string;
}

const light: ThemeColors = {
  bg: '#ecf0f1',
  surface: '#ffffff',
  text: '#2c3e50',
  textMuted: '#7f8c8d',
  border: '#dfe4ea',
  borderStrong: '#d6dbe0',
  placeholder: '#95a5a6',
  primary: BRAND.accent,
  headerBg: BRAND.gradientFrom,
  headerText: '#ffffff',
  tabActive: BRAND.accent,
  tabInactive: '#7f8c8d',
  danger: '#c0392b',
  success: '#27ae60',
};

const dark: ThemeColors = {
  bg: '#0f1419',
  surface: '#1a2230',
  text: '#ecf0f1',
  textMuted: '#95a5a6',
  border: '#2c3e50',
  borderStrong: '#3d5168',
  placeholder: '#7f8c8d',
  primary: '#8b7dff',
  headerBg: BRAND.gradientFrom,
  headerText: '#ffffff',
  tabActive: '#8b7dff',
  tabInactive: '#7f8c8d',
  danger: '#e74c3c',
  success: '#2ecc71',
};

export function useTheme(): { colors: ThemeColors; isDark: boolean } {
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  return { colors: isDark ? dark : light, isDark };
}
