// Pure receipt validation. The eventual executor must also verify private file ownership/hash.
export function verifiedCleanupBackup(manifest, receipt, now=Date.now()) {
  const hash=value=>typeof value==='string' && /^[a-f0-9]{64}$/.test(value);
  const created=Date.parse(manifest?.createdAt),verified=Date.parse(receipt?.verifiedAt);
  if(!Number.isFinite(now) || !Number.isFinite(created) || !Number.isFinite(verified)
    || created>verified || verified>now || now-created>3600000
    || manifest?.archiveListed!==true || manifest?.archiveDecoded!==true
    || manifest?.liveDatabaseChanged!==false || manifest?.restoreTested!==false
    || !Number.isSafeInteger(manifest?.bytes) || manifest.bytes<=0
    || !hash(manifest?.sha256) || receipt?.archiveSha256!==manifest.sha256
    || receipt?.database!=='tech4learn_cleanup_restore'
    || receipt?.administratorPreserved!==true || receipt?.liveDatabaseChanged!==false
    || receipt?.counts?.admins!==1
    || !['organisations','learners','attendance_sessions'].every(key=>Number.isSafeInteger(receipt?.counts?.[key]) && receipt.counts[key]>=0))
    throw new Error('A fresh, matching isolated restore receipt is required before cleanup.');
  return {archiveSha256:manifest.sha256,createdAt:manifest.createdAt,verifiedAt:receipt.verifiedAt};
}
