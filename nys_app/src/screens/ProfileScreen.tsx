import React from 'react';
import { View, Text, StyleSheet, ScrollView } from 'react-native';
import { useAuthStore } from '../store/auth';
import { useTheme } from '../theme/useTheme';

export function ProfileScreen() {
  const { colors } = useTheme();
  const user = useAuthStore((s) => s.user);
  const account = useAuthStore((s) => s.activeAccount());

  return (
    <ScrollView contentContainerStyle={[styles.c, { backgroundColor: colors.bg }]}>
      <View style={[styles.card, { backgroundColor: colors.surface }]}>
        <Label color={colors.textMuted}>Name</Label>
        <Value color={colors.text}>{user?.name} {user?.surname}</Value>

        <Label color={colors.textMuted}>Email</Label>
        <Value color={colors.text}>{user?.email}</Value>

        {user?.phone ? (
          <>
            <Label color={colors.textMuted}>Phone</Label>
            <Value color={colors.text}>{user.phone}</Value>
          </>
        ) : null}

        <Label color={colors.textMuted}>Active account</Label>
        <Value color={colors.text}>{account?.name ?? '\u2014'}</Value>
      </View>
    </ScrollView>
  );
}

function Label({ children, color }: { children: React.ReactNode; color: string }) {
  return <Text style={[styles.label, { color }]}>{children}</Text>;
}
function Value({ children, color }: { children: React.ReactNode; color: string }) {
  return <Text style={[styles.val, { color }]}>{children}</Text>;
}

const styles = StyleSheet.create({
  c: { flexGrow: 1, padding: 16 },
  card: { borderRadius: 8, padding: 16 },
  label: { fontSize: 12, marginTop: 10 },
  val: { fontSize: 16, fontWeight: '500' },
});
