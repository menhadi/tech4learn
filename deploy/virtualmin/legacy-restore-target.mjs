import { createHash } from 'node:crypto';

export function administratorRestoreProof(rows) {
  if(rows.length!==1 || !['id','email','name','password_hash'].every(key=>typeof rows[0][key]==='string' && rows[0][key].length))
    throw new Error('Exactly one complete administrator identity is required');
  const row=rows[0];
  return createHash('sha256').update(JSON.stringify([row.id,row.email,row.name,row.password_hash])).digest('hex');
}

export function legacyRestoreTarget(connection) {
  const url=new URL(connection);
  if(!['postgres:','postgresql:'].includes(url.protocol) || decodeURIComponent(url.pathname)!=='/tech4learn_app'
    || decodeURIComponent(url.username)!=='tech4learn_app')throw new Error('Unexpected source database/account');
  url.pathname='/tech4learn_cleanup_restore';
  return url;
}
