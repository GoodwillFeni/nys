import React, { useCallback, useEffect, useState } from 'react';
import { View, Text, StyleSheet, FlatList, Pressable, RefreshControl, ActivityIndicator, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { listAnimals } from '../../api/farm';
import type { Animal } from '../../types/farm';
import { Input } from '../../components/common/Input';
import type { FarmStackParamList } from '../../navigation/FarmStack';

type Nav = NativeStackNavigationProp<FarmStackParamList, 'AnimalList'>;

export function AnimalListScreen() {
  const nav = useNavigation<Nav>();
  const [animals, setAnimals] = useState<Animal[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(false);

  const load = useCallback(async (p = 1, q = search) => {
    setLoading(true);
    try {
      const res = await listAnimals({ page: p, search: q || undefined });
      setAnimals((prev) => (p === 1 ? res.data : [...prev, ...res.data]));
      setPage(res.current_page);
      setLastPage(res.last_page);
    } catch (e: any) {
      Alert.alert('Failed to load animals', e?.response?.data?.message ?? e.message);
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => { load(1, ''); }, []); // eslint-disable-line

  useEffect(() => {
    const t = setTimeout(() => load(1, search), 300);
    return () => clearTimeout(t);
  }, [search, load]);

  return (
    <View style={styles.c}>
      <View style={styles.searchRow}>
        <Input value={search} onChangeText={setSearch} placeholder="Search by tag or name" />
      </View>
      <FlatList
        data={animals}
        keyExtractor={(a) => String(a.id)}
        refreshControl={<RefreshControl refreshing={loading && page === 1} onRefresh={() => load(1, search)} />}
        onEndReached={() => { if (!loading && page < lastPage) load(page + 1, search); }}
        onEndReachedThreshold={0.4}
        ItemSeparatorComponent={() => <View style={styles.sep} />}
        renderItem={({ item }) => (
          <Pressable style={styles.row} onPress={() => nav.navigate('AnimalDetail', { animal: item })}>
            <Text style={styles.tag}>{item.animal_tag}</Text>
            <Text style={styles.name}>{item.animal_name ?? '\u2014'}</Text>
            <Text style={styles.meta}>
              {item.farm?.name ?? ''} {item.status ? `\u00b7 ${item.status}` : ''} {item.animalType ? `\u00b7 ${item.animalType.name}` : ''}
            </Text>
          </Pressable>
        )}
        ListFooterComponent={loading && page > 1 ? <ActivityIndicator style={{ marginVertical: 16 }} /> : null}
        contentContainerStyle={animals.length === 0 ? styles.emptyWrap : undefined}
        ListEmptyComponent={!loading ? <Text style={styles.empty}>No animals found.</Text> : null}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  c: { flex: 1, padding: 16, backgroundColor: '#ecf0f1' },
  searchRow: { marginBottom: 8 },
  row: { backgroundColor: '#fff', padding: 14, borderRadius: 8 },
  tag: { fontSize: 16, fontWeight: '700', color: '#2c3e50' },
  name: { fontSize: 14, color: '#34495e', marginTop: 2 },
  meta: { fontSize: 12, color: '#7f8c8d', marginTop: 4 },
  sep: { height: 8 },
  emptyWrap: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  empty: { color: '#7f8c8d' },
});
