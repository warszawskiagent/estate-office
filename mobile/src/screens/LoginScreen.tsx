import React, { useState } from 'react';
import { View, StyleSheet, KeyboardAvoidingView, Platform, ScrollView, Image } from 'react-native';
import { Text, TextInput, Button, HelperText, Surface } from 'react-native-paper';
import { useAuthStore } from '../store/useAuthStore';

export default function LoginScreen() {
  const [siteUrl, setSiteUrl] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  const { login, isLoading, error, clearError } = useAuthStore();

  const handleLogin = async () => {
    clearError();
    if (!siteUrl.trim() || !username.trim() || !password) return;

    let url = siteUrl.trim();
    if (!url.startsWith('http')) url = 'https://' + url;

    await login(url, username.trim(), password);
  };

  return (
    <KeyboardAvoidingView
      style={styles.root}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        <View style={styles.logoContainer}>
          <Text style={styles.logoText}>EstateOffice</Text>
          <Text style={styles.logoSub}>CRM</Text>
        </View>

        <Surface style={styles.card} elevation={2}>
          <Text variant="headlineSmall" style={styles.title}>Logowanie</Text>

          <TextInput
            label="Adres strony WordPress"
            placeholder="np. mojafirma.pl"
            value={siteUrl}
            onChangeText={setSiteUrl}
            mode="outlined"
            style={styles.input}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="url"
            left={<TextInput.Icon icon="web" />}
          />

          <TextInput
            label="Login"
            value={username}
            onChangeText={setUsername}
            mode="outlined"
            style={styles.input}
            autoCapitalize="none"
            autoCorrect={false}
            left={<TextInput.Icon icon="account" />}
          />

          <TextInput
            label="Hasło"
            value={password}
            onChangeText={setPassword}
            mode="outlined"
            style={styles.input}
            secureTextEntry={!showPassword}
            left={<TextInput.Icon icon="lock" />}
            right={
              <TextInput.Icon
                icon={showPassword ? 'eye-off' : 'eye'}
                onPress={() => setShowPassword(!showPassword)}
              />
            }
          />

          {error && (
            <HelperText type="error" visible style={styles.errorText}>
              {error}
            </HelperText>
          )}

          <Button
            mode="contained"
            onPress={handleLogin}
            loading={isLoading}
            disabled={isLoading || !siteUrl.trim() || !username.trim() || !password}
            style={styles.button}
            buttonColor="#1a237e"
            contentStyle={styles.buttonContent}
          >
            Zaloguj się
          </Button>
        </Surface>

        <Text style={styles.version}>EstateOffice CRM Mobile v0.1</Text>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#1a237e' },
  scroll: { flexGrow: 1, justifyContent: 'center', padding: 24 },
  logoContainer: { alignItems: 'center', marginBottom: 32 },
  logoText: { fontSize: 36, fontWeight: '700', color: '#fff', letterSpacing: 1 },
  logoSub: { fontSize: 18, color: '#90caf9', fontWeight: '300', letterSpacing: 6, marginTop: -4 },
  card: { borderRadius: 12, padding: 24, backgroundColor: '#fff' },
  title: { marginBottom: 20, color: '#1a237e', fontWeight: '600' },
  input: { marginBottom: 12 },
  errorText: { marginBottom: 8, fontSize: 13 },
  button: { marginTop: 8, borderRadius: 8 },
  buttonContent: { paddingVertical: 6 },
  version: { textAlign: 'center', color: '#90caf9', fontSize: 11, marginTop: 24 },
});
