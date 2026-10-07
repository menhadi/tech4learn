// Read-only guard. Never stops/restarts a service or terminates database sessions.
import {spawnSync} from 'node:child_process';
export async function assertLegacyWritesPaused(sql, observeService=()=>spawnSync('/usr/bin/systemctl',['is-active','tech4learn'],{encoding:'utf8',timeout:5000})) {
 const state=observeService();
 if(state.error || state.status!==3 || state.stdout?.trim()!=='inactive')throw new Error('The Tech4Learn API must be explicitly stopped before cleanup.');
 const sessions=await sql.query("SELECT count(*)::integer AS total FROM pg_stat_activity WHERE datname=current_database() AND pid<>pg_backend_pid() AND backend_type='client backend'");
 if(sessions.rows[0]?.total!==0)throw new Error('Other database clients remain; reviewed writes must be paused before cleanup.');
}
