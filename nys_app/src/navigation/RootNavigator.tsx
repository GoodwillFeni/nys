import React, { useEffect } from 'react';
import { NavigationContainer, DefaultTheme, DarkTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createDrawerNavigator } from '@react-navigation/drawer';
import { ActivityIndicator, View } from 'react-native';
import { useAuthStore } from '../store/auth';
import { useTheme } from '../theme/useTheme';
import { LoginScreen } from '../screens/auth/LoginScreen';
import { AccountSelectScreen } from '../screens/auth/AccountSelectScreen';
import { BLEScanScreen } from '../screens/ble/BLEScanScreen';
import { BLEConfigScreen } from '../screens/ble/BLEConfigScreen';
import { FarmStack } from './FarmStack';
import { ShopStack } from './ShopStack';
import { POSScreen } from '../screens/shop/POSScreen';
import { ProfileScreen } from '../screens/ProfileScreen';
import { DrawerContent } from './DrawerContent';
import { HamburgerHeader } from './HamburgerBtn';
import {
  canBLE, canFarm, canPOS, canShopAdmin, canShopCustomer,
} from '../auth/permissions';

export type BLEStackParamList = {
  BLEScan: undefined;
  BLEConfig: { deviceId: string; deviceUid: string; deviceName: string };
};

const Stack = createNativeStackNavigator();
const Drawer = createDrawerNavigator();
const BLEStack = createNativeStackNavigator<BLEStackParamList>();

function BLEStackNavigator() {
  const { colors } = useTheme();
  return (
    <BLEStack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: colors.headerBg },
        headerTintColor: colors.headerText,
        headerTitleStyle: { fontWeight: '600' },
      }}
    >
      <BLEStack.Screen
        name="BLEScan"
        component={BLEScanScreen}
        options={{ title: 'Devices', headerLeft: () => <HamburgerHeader tintColor="#fff" /> }}
      />
      <BLEStack.Screen name="BLEConfig" component={BLEConfigScreen} options={{ title: 'Configure Device' }} />
    </BLEStack.Navigator>
  );
}

function MainDrawer() {
  const { colors } = useTheme();
  const account = useAuthStore((s) => s.activeAccount());
  const isSuper = useAuthStore((s) => !!s.user?.is_super_admin);

  // Super admins bypass all permission checks (mirror backend behavior)
  const bypass = isSuper;

  return (
    <Drawer.Navigator
      drawerContent={(props) => <DrawerContent {...props} />}
      screenOptions={{
        headerStyle: { backgroundColor: colors.headerBg },
        headerTintColor: colors.headerText,
        headerTitleStyle: { fontWeight: '600' },
        drawerType: 'front',
        headerLeft: () => <HamburgerHeader tintColor={colors.headerText} />,
      }}
    >
      {(bypass || canFarm(account)) && (
        <Drawer.Screen
          name="Farm"
          component={FarmStack}
          initialParams={{ icon: 'leaf-outline' }}
          options={{ headerShown: false, drawerLabel: 'Farm' }}
        />
      )}
      {(bypass || canBLE(account)) && (
        <Drawer.Screen
          name="Devices"
          component={BLEStackNavigator}
          initialParams={{ icon: 'bluetooth-outline' }}
          options={{ headerShown: false, drawerLabel: 'Devices' }}
        />
      )}
      {(bypass || canPOS(account)) && (
        <Drawer.Screen
          name="POS"
          component={POSScreen}
          initialParams={{ icon: 'card-outline' }}
          options={{ drawerLabel: 'Point of Sale', title: 'POS' }}
        />
      )}
      {(bypass || canShopAdmin(account) || canShopCustomer(account)) && (
        <Drawer.Screen
          name="Shop"
          component={ShopStack}
          initialParams={{ icon: 'cart-outline' }}
          options={{ headerShown: false, drawerLabel: 'Shop' }}
        />
      )}
      <Drawer.Screen
        name="Profile"
        component={ProfileScreen}
        initialParams={{ icon: 'person-outline' }}
        options={{ drawerLabel: 'Profile' }}
      />
    </Drawer.Navigator>
  );
}

export function RootNavigator() {
  const { token, hydrated, activeAccountId, accounts, hydrate } = useAuthStore();
  const { colors, isDark } = useTheme();

  useEffect(() => {
    if (!hydrated) hydrate();
  }, [hydrated, hydrate]);

  const navTheme = {
    ...(isDark ? DarkTheme : DefaultTheme),
    colors: {
      ...(isDark ? DarkTheme : DefaultTheme).colors,
      background: colors.bg,
      card: colors.surface,
      text: colors.text,
      primary: colors.primary,
      border: colors.border,
    },
  };

  if (!hydrated) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.bg }}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  return (
    <NavigationContainer theme={navTheme}>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {!token ? (
          <Stack.Screen name="Login" component={LoginScreen} />
        ) : !activeAccountId || accounts.length === 0 ? (
          <Stack.Screen name="AccountSelect" component={AccountSelectScreen} />
        ) : (
          <Stack.Screen name="Main" component={MainDrawer} />
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}
