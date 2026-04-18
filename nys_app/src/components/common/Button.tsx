import React from 'react';
import { Pressable, Text, StyleSheet, ActivityIndicator, PressableProps } from 'react-native';
import { useTheme } from '../../theme/useTheme';

interface Props extends PressableProps {
  title: string;
  loading?: boolean;
  variant?: 'primary' | 'secondary' | 'danger';
}

export function Button({ title, loading, variant = 'primary', disabled, style, ...rest }: Props) {
  const { colors } = useTheme();
  const bg =
    variant === 'danger'    ? colors.danger :
    variant === 'secondary' ? colors.textMuted :
                              colors.primary;
  return (
    <Pressable
      {...rest}
      disabled={disabled || loading}
      style={[
        styles.btn,
        { backgroundColor: bg, opacity: disabled || loading ? 0.6 : 1 },
        style as any,
      ]}
    >
      {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.txt}>{title}</Text>}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  btn: {
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 25,
    alignItems: 'center',
    justifyContent: 'center',
  },
  txt: { color: '#fff', fontWeight: '600', fontSize: 15 },
});
