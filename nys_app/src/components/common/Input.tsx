import React, { useState } from 'react';
import { TextInput, View, Text, StyleSheet, TextInputProps, Pressable } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useTheme } from '../../theme/useTheme';

interface Props extends TextInputProps {
  label?: string;
  error?: string;
  /** Use "pill" to match the rounded login style (border-radius: 25px). */
  variant?: 'default' | 'pill';
}

export function Input({ label, error, variant = 'default', style, secureTextEntry, ...rest }: Props) {
  const { colors } = useTheme();
  const [hidden, setHidden] = useState(!!secureTextEntry);
  const isPill = variant === 'pill';
  const showing = secureTextEntry ? hidden : false;

  return (
    <View style={styles.wrap}>
      {label && <Text style={[styles.label, { color: colors.textMuted }]}>{label}</Text>}
      <View
        style={[
          styles.inputWrap,
          {
            borderColor: error ? colors.danger : colors.borderStrong,
            backgroundColor: colors.surface,
            borderRadius: isPill ? 25 : 8,
            paddingHorizontal: isPill ? 15 : 12,
          },
        ]}
      >
        <TextInput
          {...rest}
          secureTextEntry={showing}
          placeholderTextColor={colors.placeholder}
          style={[styles.input, { color: colors.text }, style as any]}
        />
        {secureTextEntry && (
          <Pressable
            onPress={() => setHidden((h) => !h)}
            hitSlop={10}
            style={styles.eye}
            accessibilityLabel={showing ? 'Show password' : 'Hide password'}
          >
            <Ionicons
              name={showing ? 'eye-outline' : 'eye-off-outline'}
              size={20}
              color="#6a5cff"
            />
          </Pressable>
        )}
      </View>
      {error && <Text style={[styles.err, { color: colors.danger }]}>{error}</Text>}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 20 },
  label: { fontSize: 13, marginBottom: 4, fontWeight: '500' },
  inputWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
  },
  input: { flex: 1, paddingVertical: 12, fontSize: 14 },
  eye: { paddingLeft: 8, paddingVertical: 4 },
  err: { fontSize: 12, marginTop: 4 },
});
