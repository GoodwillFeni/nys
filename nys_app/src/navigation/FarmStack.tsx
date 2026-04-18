import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { FarmDashboardScreen } from '../screens/farm/FarmDashboardScreen';
import { AnimalListScreen } from '../screens/farm/AnimalListScreen';
import { AnimalDetailScreen } from '../screens/farm/AnimalDetailScreen';
import { LogEventScreen } from '../screens/farm/LogEventScreen';
import { AnimalEditScreen } from '../screens/farm/AnimalEditScreen';
import { InventoryMovementScreen } from '../screens/farm/InventoryMovementScreen';
import { useTheme } from '../theme/useTheme';
import { HamburgerHeader } from './HamburgerBtn';
import type { Animal } from '../types/farm';

export type FarmStackParamList = {
  FarmDashboard: undefined;
  AnimalList: undefined;
  AnimalDetail: { animal: Animal };
  LogEvent: { animal: Animal };
  AnimalEdit: { animal: Animal };
  InventoryMovement: { animal?: Animal };
};

const Stack = createNativeStackNavigator<FarmStackParamList>();

export function FarmStack() {
  const { colors } = useTheme();
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: colors.headerBg },
        headerTintColor: colors.headerText,
        headerTitleStyle: { fontWeight: '600' },
      }}
    >
      <Stack.Screen
        name="FarmDashboard"
        component={FarmDashboardScreen}
        options={{ title: 'Farm', headerLeft: () => <HamburgerHeader tintColor="#fff" /> }}
      />
      <Stack.Screen name="AnimalList" component={AnimalListScreen} options={{ title: 'Animals' }} />
      <Stack.Screen name="AnimalDetail" component={AnimalDetailScreen} options={{ title: 'Animal' }} />
      <Stack.Screen name="LogEvent" component={LogEventScreen} options={{ title: 'Log event' }} />
      <Stack.Screen name="AnimalEdit" component={AnimalEditScreen} options={{ title: 'Edit animal' }} />
      <Stack.Screen name="InventoryMovement" component={InventoryMovementScreen} options={{ title: 'Inventory movement' }} />
    </Stack.Navigator>
  );
}
