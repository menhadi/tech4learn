export function legacyRestoreTarget(connection) {
  const url=new URL(connection);
  if(!['postgres:','postgresql:'].includes(url.protocol) || decodeURIComponent(url.pathname)!=='/tech4learn_app'
    || decodeURIComponent(url.username)!=='tech4learn_app')throw new Error('Unexpected source database/account');
  url.pathname='/tech4learn_cleanup_restore';
  return url;
}
