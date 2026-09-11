import { StatusBar } from 'expo-status-bar';
import { StyleSheet, Text, View } from 'react-native';

export default function App() {
  return (
    <View style={styles.container}>
      <Text style={styles.brand}>TECH4LEARN</Text>
      <Text style={styles.title}>More time for learning.</Text>
      <Text style={styles.body}>Your workspace is taking shape. Organisation login and attendance capture will be added next.</Text>
      <Text style={styles.note}>Development scaffold. Camera and location are not collected in this version.</Text>
      <StatusBar style="dark" />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f7f3',
    padding: 28,
    justifyContent: 'center',
  },
  brand: { color: '#175d50', fontWeight: '800', letterSpacing: 2, fontSize: 14 },
  title: { color: '#173b38', fontSize: 38, fontWeight: '700', marginTop: 28 },
  body: { color: '#173b38', fontSize: 18, lineHeight: 28, marginTop: 24 },
  note: { color: '#506963', fontSize: 14, lineHeight: 22, marginTop: 24 },
});
