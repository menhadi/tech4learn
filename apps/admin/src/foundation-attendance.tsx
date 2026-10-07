import { createRoot } from 'react-dom/client';
import { FoundationAttendance } from './FoundationAttendance';
import './styles.css';
import './admin-theme.css';
import './foundation-attendance.css';

const root=document.getElementById('foundation-attendance');
if(root)createRoot(root).render(<div className="workspace foundation-embedded"><FoundationAttendance/></div>);
