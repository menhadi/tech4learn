import { StrictMode, useState } from 'react';
import { createRoot } from 'react-dom/client';
import type { HealthResponse } from '@tech4learn/contracts';
import './styles.css';

function App() {
  const [status, setStatus] = useState('Connection not checked');
  const [busy, setBusy] = useState(false);

  async function checkConnection() {
    setBusy(true);
    setStatus('Checking connection…');
    try {
      const url = import.meta.env.VITE_API_URL || 'http://localhost:3000/api/v1';
      const response = await fetch(`${url.replace(/\/$/, '')}/health`, {
        signal: AbortSignal.timeout(5000),
      });
      if (!response.ok) throw new Error('API unavailable');
      const data = (await response.json()) as Partial<HealthResponse>;
      if (data.status !== 'ok' || data.service !== 'tech4learn-api') {
        throw new Error('Unexpected response');
      }
      setStatus('API connected. Organisation features are not yet available.');
    } catch {
      setStatus('Unable to connect. Check the API address and that the server is running.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <main>
      <span className="brand">TECH4LEARN</span>
      <p className="eyebrow">Platform foundation</p>
      <h1>More time for learning.</h1>
      <p className="intro">A shared platform for education programmes, with each organisation’s own identity and ways of working.</p>
      <section aria-labelledby="status-heading">
        <h2 id="status-heading">Administration is taking shape</h2>
        <p>This is the initial application shell. Login, organisation setup, attendance, learning assessments, and ExamElite integration are planned next.</p>
        <button onClick={checkConnection} disabled={busy}>{busy ? 'Checking…' : 'Check API connection'}</button>
        <p role="status">{status}</p>
      </section>
      <footer>Development scaffold · No learner records are collected here.</footer>
    </main>
  );
}

createRoot(document.getElementById('root')!).render(<StrictMode><App /></StrictMode>);
