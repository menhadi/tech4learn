// Called inside the same cleanup transaction, never a standalone live command.
export async function retireLegacyAccounts(sql,reviewedUserIds,administratorId) {
  const candidates=(await sql.query(`SELECT id FROM users WHERE id=ANY($1::uuid[]) AND id<>$2 AND NOT is_superadmin
    AND NOT EXISTS(SELECT 1 FROM memberships m WHERE m.user_id=users.id) FOR UPDATE`,[reviewedUserIds,administratorId])).rows.map(r=>r.id);
  if(!candidates.length)return 0;
  await sql.query('DELETE FROM sessions WHERE user_id=ANY($1::uuid[])',[candidates]);
  // Retain global operational history without dangling identities of retired staff.
  await sql.query('UPDATE audit_events SET actor_id=NULL WHERE actor_id=ANY($1::uuid[]) AND organisation_id IS NULL',[candidates]);
  const references=await sql.query(`SELECT n.nspname AS schema,t.relname AS table_name,a.attname AS column_name,
    cardinality(c.conkey) AS key_columns FROM pg_constraint c
    JOIN pg_class t ON t.oid=c.conrelid JOIN pg_namespace n ON n.oid=t.relnamespace
    JOIN pg_attribute a ON a.attrelid=c.conrelid AND a.attnum=c.conkey[1]
    WHERE c.contype='f' AND c.confrelid='public.users'::regclass`);
  const quote=value=>'"'+value.replaceAll('"','""')+'"';
  for(const reference of references.rows) {
    if(reference.schema!=='public' || reference.key_columns!==1)throw new Error('Account dependency requires explicit review');
    const count=await sql.query(`SELECT count(*)::integer AS count FROM public.${quote(reference.table_name)} WHERE ${quote(reference.column_name)}=ANY($1::uuid[])`,[candidates]);
    if(count.rows[0].count)throw new Error('Retained account records require explicit review: '+reference.table_name);
  }
  await sql.query('DELETE FROM users WHERE id=ANY($1::uuid[]) AND NOT is_superadmin',[candidates]);
  return candidates.length;
}
