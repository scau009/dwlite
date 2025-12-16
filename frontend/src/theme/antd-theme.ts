import { theme, type ThemeConfig } from 'antd';

// Primary color: Indigo
const primaryColor = '#6366f1';

export const lightTheme: ThemeConfig = {
  token: {
    colorPrimary: primaryColor,
    colorLink: primaryColor,
    borderRadius: 6,
    fontSize: 14,
    colorBgLayout: '#f5f5f5',
    colorBgContainer: '#ffffff',
  },
  components: {
    Layout: {
      headerBg: '#ffffff',
      siderBg: '#ffffff',
      headerHeight: 56,
      headerPadding: '0 24px',
    },
    Menu: {
      itemBg: 'transparent',
      subMenuItemBg: 'transparent',
    },
    Table: {
      headerBg: '#fafafa',
    },
    Card: {
      paddingLG: 24,
    },
  },
};

export const darkTheme: ThemeConfig = {
  algorithm: theme.darkAlgorithm,
  token: {
    colorPrimary: primaryColor,
    colorLink: primaryColor,
    borderRadius: 6,
    fontSize: 14,
  },
  components: {
    Layout: {
      headerHeight: 56,
      headerPadding: '0 24px',
    },
    Card: {
      paddingLG: 24,
    },
  },
};
