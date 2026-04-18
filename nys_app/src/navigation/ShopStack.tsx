import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { ShopScreen } from '../screens/shop/ShopScreen';
import { PlaceOrderScreen } from '../screens/shop/PlaceOrderScreen';
import { MyOrdersScreen } from '../screens/shop/MyOrdersScreen';
import { AllOrdersScreen } from '../screens/shop/AllOrdersScreen';
import { useTheme } from '../theme/useTheme';
import { HamburgerHeader } from './HamburgerBtn';

export type ShopStackParamList = {
  ProductList: undefined;
  PlaceOrder: { items: { product_id: number; qty: number }[] };
  MyOrders: undefined;
  AllOrders: undefined;
};

const Stack = createNativeStackNavigator<ShopStackParamList>();

export function ShopStack() {
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
        name="ProductList"
        component={ShopScreen}
        options={{ title: 'Shop', headerLeft: () => <HamburgerHeader tintColor="#fff" /> }}
      />
      <Stack.Screen name="PlaceOrder" component={PlaceOrderScreen} options={{ title: 'Place order' }} />
      <Stack.Screen name="MyOrders" component={MyOrdersScreen} options={{ title: 'My orders' }} />
      <Stack.Screen name="AllOrders" component={AllOrdersScreen} options={{ title: 'All orders' }} />
    </Stack.Navigator>
  );
}
