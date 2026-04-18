import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, Alert } from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { Input } from '../../components/common/Input';
import { Button } from '../../components/common/Button';
import { updateAnimal } from '../../api/farm';
import type { FarmStackParamList } from '../../navigation/FarmStack';

type Rte = RouteProp<FarmStackParamList, 'AnimalEdit'>;

export function AnimalEditScreen() {
  const { animal } = useRoute<Rte>().params;
  const nav = useNavigation();

  const [tag, setTag] = useState(animal.animal_tag);
  const [name, setName] = useState(animal.animal_name ?? '');
  const [sex, setSex] = useState(animal.sex ?? '');
  const [dob, setDob] = useState(animal.date_of_birth ?? '');
  const [notes, setNotes] = useState(animal.notes ?? '');
  const [submitting, setSubmitting] = useState(false);

  const submit = async () => {
    setSubmitting(true);
    try {
      await updateAnimal(animal.id, {
        animal_tag: tag,
        name: name || undefined,
        sex: sex || undefined,
        date_of_birth: dob || undefined,
        notes: notes || undefined,
      });
      Alert.alert('Saved', 'Animal updated.', [{ text: 'OK', onPress: () => nav.goBack() }]);
    } catch (e: any) {
      Alert.alert('Save failed', e?.response?.data?.message ?? e.message);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ScrollView contentContainerStyle={styles.c}>
      <Text style={styles.h}>Edit {animal.animal_tag}</Text>
      <Input label="Animal tag" value={String(tag)} onChangeText={setTag} keyboardType="numeric" />
      <Input label="Name" value={name} onChangeText={setName} />
      <Input label="Sex" value={sex} onChangeText={setSex} />
      <Input label="Date of birth (YYYY-MM-DD)" value={dob} onChangeText={setDob} />
      <Input label="Notes" value={notes} onChangeText={setNotes} multiline />
      <Button title="Save changes" loading={submitting} onPress={submit} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  c: { padding: 16, backgroundColor: '#ecf0f1' },
  h: { fontSize: 16, fontWeight: '700', color: '#2c3e50', marginBottom: 12 },
});
