import React from 'react';
import { View, Text, StyleSheet, FlatList, Pressable } from 'react-native';
import { useAuthStore } from '../../store/auth';
import type { Account } from '../../types';

export function AccountSelectScreen() {
  const accounts = useAuthStore((s) => s.accounts);
  const setActiveAccount = useAuthStore((s) => s.setActiveAccount);

  const renderItem = ({ item }: { item: Account }) => (
    <Pressable style={styles.row} onPress={() => setActiveAccount(item.id)}>
      <View>
        <Text style={styles.name}>{item.name}</Text>
        {item.type ? <Text style={styles.meta}>{item.type}</Text> : null}
      </View>
    </Pressable>
  );

  return (
    <View style={styles.container}>
      <Text style={styles.header}>Choose an account</Text>
      <FlatList
        data={accounts}
        keyExtractor={(a) => String(a.id)}
        renderItem={renderItem}
        ItemSeparatorComponent={() => <View style={styles.sep} />}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 16, backgroundColor: '#ecf0f1' },
  header: { fontSize: 20, fontWeight: '600', color: '#2c3e50', marginBottom: 16 },
  row: { padding: 16, backgroundColor: '#fff', borderRadius: 8 },
  name: { fontSize: 16, fontWeight: '600', color: '#2c3e50' },
  meta: { fontSize: 13, color: '#7f8c8d', marginTop: 2 },
  sep: { height: 8 },
});
