import React from 'react';
import { Pressable } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { DrawerActions, useNavigation } from '@react-navigation/native';

export function HamburgerHeader({ tintColor }: { tintColor?: string }) {
  const navigation = useNavigation();
  return (
    <Pressable
      onPress={() => navigation.dispatch(DrawerActions.openDrawer())}
      hitSlop={12}
      style={{ marginLeft: 16, padding: 4 }}
    >
      <Ionicons name="menu" size={24} color={tintColor ?? '#fff'} />
    </Pressable>
  );
}
