import React from 'react';
import { View, Text, StyleSheet, Pressable, Alert } from 'react-native';
import { DrawerContentScrollView, DrawerContentComponentProps } from '@react-navigation/drawer';
import { Ionicons } from '@expo/vector-icons';
import Constants from 'expo-constants';
import { useAuthStore } from '../store/auth';
import { useTheme, BRAND } from '../theme/useTheme';
import { LinearGradient } from 'expo-linear-gradient';

const APP_VERSION = 'NYS V_0.0.1';

export function DrawerContent(props: DrawerContentComponentProps) {
  const { colors } = useTheme();
  const user = useAuthStore((s) => s.user);
  const accounts = useAuthStore((s) => s.accounts);
  const activeAccountId = useAuthStore((s) => s.activeAccountId);
  const role = useAuthStore((s) => s.activeRole());
  const logout = useAuthStore((s) => s.logout);
  const active = accounts.find((a) => a.id === activeAccountId);

  const confirmLogout = () => {
    Alert.alert('Sign out', 'Are you sure you want to sign out?', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Sign out', style: 'destructive', onPress: () => logout() },
    ]);
  };

  const currentRoute = props.state.routes[props.state.index]?.name;

  return (
    <View style={[styles.root, { backgroundColor: colors.surface }]}>
      {/* Header */}
      <LinearGradient colors={[BRAND.gradientFrom, BRAND.gradientTo]} style={styles.header}>
        <View style={styles.avatar}>
          <Text style={styles.avatarTxt}>
            {(user?.name?.[0] ?? '?').toUpperCase()}
          </Text>
        </View>
        <Text style={styles.userName}>{user?.name} {user?.surname}</Text>
        <Text style={styles.userMeta}>{user?.email}</Text>
        <View style={styles.badgeRow}>
          <Text style={styles.badge}>{active?.name ?? 'No account'}</Text>
          {role && <Text style={styles.badge}>{role}</Text>}
        </View>
      </LinearGradient>

      {/* Nav items */}
      <DrawerContentScrollView {...props} contentContainerStyle={{ paddingTop: 8 }}>
        {props.state.routes.map((route, i) => {
          const focused = currentRoute === route.name;
          const opts = props.descriptors[route.key].options;
          const label = typeof opts.drawerLabel === 'string' ? opts.drawerLabel : (opts.title ?? route.name);
          const iconName = (route as any).params?.icon as keyof typeof Ionicons.glyphMap
            ?? 'ellipse-outline';
          return (
            <Pressable
              key={route.key}
              onPress={() => props.navigation.navigate(route.name)}
              style={[styles.item, focused && { backgroundColor: colors.primary + '22' }]}
            >
              <Ionicons
                name={iconName}
                size={22}
                color={focused ? colors.primary : colors.textMuted}
              />
              <Text style={[styles.itemLabel, { color: focused ? colors.primary : colors.text }]}>
                {label as string}
              </Text>
            </Pressable>
          );
        })}

        <View style={[styles.sep, { backgroundColor: colors.border }]} />

        <Pressable onPress={confirmLogout} style={styles.item}>
          <Ionicons name="log-out-outline" size={22} color={colors.danger} />
          <Text style={[styles.itemLabel, { color: colors.danger }]}>Sign out</Text>
        </Pressable>
      </DrawerContentScrollView>

      {/* Footer — app version */}
      <View style={[styles.footer, { borderTopColor: colors.border }]}>
        <Text style={[styles.footerTxt, { color: colors.textMuted }]}>{APP_VERSION}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  header: { padding: 20, paddingTop: 50 },
  avatar: {
    width: 56, height: 56, borderRadius: 28,
    backgroundColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center', justifyContent: 'center',
    marginBottom: 10,
  },
  avatarTxt: { color: '#fff', fontSize: 22, fontWeight: '700' },
  userName: { color: '#fff', fontSize: 16, fontWeight: '600' },
  userMeta: { color: 'rgba(255,255,255,0.8)', fontSize: 12, marginTop: 2 },
  badgeRow: { flexDirection: 'row', gap: 6, marginTop: 10, flexWrap: 'wrap' },
  badge: {
    color: '#fff', fontSize: 11, fontWeight: '600',
    backgroundColor: 'rgba(255,255,255,0.2)',
    paddingHorizontal: 8, paddingVertical: 3, borderRadius: 10,
    overflow: 'hidden',
  },
  item: {
    flexDirection: 'row', alignItems: 'center',
    paddingVertical: 12, paddingHorizontal: 16,
    marginHorizontal: 8, borderRadius: 8,
    gap: 14,
  },
  itemLabel: { fontSize: 15, fontWeight: '500' },
  sep: { height: StyleSheet.hairlineWidth, marginVertical: 8, marginHorizontal: 16 },
  footer: {
    paddingVertical: 14, alignItems: 'center',
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  footerTxt: { fontSize: 12, fontWeight: '500', letterSpacing: 0.5 },
});
