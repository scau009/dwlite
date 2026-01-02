import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router';
import { ConfigProvider, App } from 'antd';
import { useTranslation } from 'react-i18next';
import zhCN from 'antd/locale/zh_CN';
import enUS from 'antd/locale/en_US';
import dayjs from 'dayjs';
import 'dayjs/locale/zh-cn';

import { AuthProvider } from '@/contexts/auth-context';
import { ThemeProvider, useTheme } from '@/contexts/theme-context';
import { lightTheme, darkTheme } from '@/theme/antd-theme';
import { router } from '@/router';
import '@/i18n';
import './index.css';

// eslint-disable-next-line react-refresh/only-export-components
function AppWithTheme() {
  const { i18n } = useTranslation();
  const { isDark } = useTheme();

  // Set dayjs locale
  dayjs.locale(i18n.language === 'zh' ? 'zh-cn' : 'en');

  const antdLocale = i18n.language === 'zh' ? zhCN : enUS;
  const currentTheme = isDark ? darkTheme : lightTheme;

  return (
    <ConfigProvider locale={antdLocale} theme={currentTheme}>
      <App>
        <AuthProvider>
          <RouterProvider router={router} />
        </AuthProvider>
      </App>
    </ConfigProvider>
  );
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ThemeProvider>
      <AppWithTheme />
    </ThemeProvider>
  </StrictMode>
);
