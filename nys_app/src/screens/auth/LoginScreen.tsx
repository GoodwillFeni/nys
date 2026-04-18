import React, { useState } from 'react';
import { View, Text, StyleSheet, Alert, Pressable, ActivityIndicator } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { KeyboardAwareScrollView } from 'react-native-keyboard-aware-scroll-view';
import { Input } from '../../components/common/Input';
import { login } from '../../api/auth';
import { useAuthStore } from '../../store/auth';

const GRADIENT: [string, string] = ['#27253f', '#605a6d'];
const ACCENT = '#6a5cff';

export function LoginScreen() {
  const [loginId, setLoginId] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const setSession = useAuthStore((s) => s.setSession);

  const onSubmit = async () => {
    if (!loginId || !password) {
      Alert.alert('Missing info', 'Please enter email/phone and password');
      return;
    }
    setLoading(true);
    try {
      const res = await login(loginId, password);
      await setSession(res.token, res.user, res.accounts);
    } catch (e: any) {
      Alert.alert('Login failed', e?.response?.data?.message ?? 'Check your credentials');
    } finally {
      setLoading(false);
    }
  };

  return (
    <LinearGradient colors={GRADIENT} style={styles.gradient}>
      <KeyboardAwareScrollView
        contentContainerStyle={styles.scroll}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
        enableOnAndroid
        enableResetScrollToCoords={false}
        extraScrollHeight={80}
        extraHeight={120}
      >
        <View style={styles.card}>
          <View style={styles.form}>
            <Text style={styles.formTitle}>User Login</Text>

            <Input
              value={loginId}
              onChangeText={setLoginId}
              placeholder="Email or phone"
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="email-address"
              variant="pill"
              returnKeyType="next"
            />
            <Input
              value={password}
              onChangeText={setPassword}
              placeholder="Password"
              secureTextEntry
              variant="pill"
              returnKeyType="go"
              onSubmitEditing={onSubmit}
            />

            <View style={styles.options}>
              <Pressable onPress={() => Alert.alert('Forgot password', 'Use the web portal for now.')}>
                <Text style={styles.link}>Forgot password?</Text>
              </Pressable>
            </View>

            <Pressable onPress={onSubmit} disabled={loading} style={{ opacity: loading ? 0.7 : 1 }}>
              <LinearGradient colors={GRADIENT} style={styles.submit}>
                {loading ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.submitText}>Login</Text>
                )}
              </LinearGradient>
            </Pressable>

            <View style={styles.switch}>
              <Text style={styles.switchText}>Do not have an account? </Text>
              <Pressable onPress={() => Alert.alert('Sign up', 'Use the web portal to create an account.')}>
                <Text style={styles.link}>Sign Up</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </KeyboardAwareScrollView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  gradient: { flex: 1 },
  scroll: { flexGrow: 1, justifyContent: 'center', padding: 16 },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 20 },
    shadowOpacity: 0.2,
    shadowRadius: 40,
    elevation: 10,
  },
  hero: { padding: 30 },
  heroTitle: { fontSize: 28, fontWeight: '700', color: '#fff', marginBottom: 10 },
  heroSub: { color: '#fff', lineHeight: 22, opacity: 0.9, fontSize: 14 },
  form: { padding: 28 },
  formTitle: { fontSize: 22, fontWeight: '600', color: ACCENT, textAlign: 'center', marginBottom: 24 },
  options: { flexDirection: 'row', justifyContent: 'flex-end', marginBottom: 20 },
  link: { color: ACCENT, fontSize: 13, fontWeight: '500' },
  submit: {
    paddingVertical: 14,
    borderRadius: 25,
    alignItems: 'center',
    justifyContent: 'center',
  },
  submitText: { color: '#fff', fontSize: 15, fontWeight: '600' },
  switch: { flexDirection: 'row', justifyContent: 'center', marginTop: 15 },
  switchText: { fontSize: 13, color: '#34495e' },
});
