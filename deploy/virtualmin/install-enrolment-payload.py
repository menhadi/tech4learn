"""Install a reviewed, hash-bound Tech4Learn payload with private file rollback copies."""
import hashlib, json, os, pathlib, re, shutil, sys

BASE = pathlib.Path('/home/tech4learn/releases/551654c4e4feb1708d24f638e2da8068cee430d7')
PRIVATE = pathlib.Path('/home/tech4learn/native-shared')
NATIVE = {
    'app/Support/AttendanceBridge.php', 'app/Http/Controllers/AttendanceBridgeController.php',
    'app/Http/Controllers/StudentAdminController.php', 'resources/views/students/admin/index.blade.php',
    'app/Services/EnrolledStudentEditGuard.php', 'app/Http/Controllers/Students/ApiStudentProfileController.php',
    'app/Http/Controllers/Students/StudentsController.php',
    'app/Services/EnrolledStudentProfile.php', 'app/Services/StudentDeliveryReceipt.php',
    'app/Services/StudentAdmission.php',
    'app/Http/Controllers/Students/StudentAuthController.php', 'app/Http/Controllers/Students/ApiStudentAuthController.php',
    'database/migrations/2026_10_09_000008_create_foundation_student_admissions.php',
    'app/Services/EnrolledStudentGroup.php', 'app/Console/Commands/PrepareStudentDeliveryKey.php',
    'database/migrations/2026_10_08_000006_create_foundation_student_profiles.php',
    'database/migrations/2026_10_08_000007_create_foundation_section_groups.php',
    'config/attendance.php', 'routes/web.php',
}

def allowed(name):
    return (name in {'platform/'+p for p in NATIVE}
        or re.fullmatch(r'(apps/api/dist|packages/contracts/dist)/[A-Za-z0-9_.-]+', name)
        or re.fullmatch(r'platform/public/attendance-ui/(manifest.json|assets/[A-Za-z0-9_.-]+)', name))

try:
    if sys.platform != 'linux' or os.geteuid() == 0 or len(sys.argv) != 2:
        raise ValueError()
    stage = pathlib.Path(sys.argv[1])
    if stage.parent != PRIVATE or stage.is_symlink() or stage.resolve() != stage or not stage.name.startswith('enrolment-payload-'):
        raise ValueError()
    if BASE.resolve() != BASE or BASE.stat().st_uid != os.geteuid() or PRIVATE.stat().st_mode & 0o077:
        raise ValueError()
    manifest = json.loads((stage/'manifest.json').read_text())
    revision = manifest['revision']
    if not re.fullmatch('[a-f0-9]{40}', revision) or not isinstance(manifest['files'], dict):
        raise ValueError()
    files = manifest['files']
    for name, digest in files.items():
        source, target = stage/name, BASE/name
        if not allowed(name) or source.is_symlink() or source.resolve() != source or not source.is_file():
            raise ValueError()
        if not target.resolve().is_relative_to(BASE) or target.is_symlink():
            raise ValueError()
        if hashlib.sha256(source.read_bytes()).hexdigest() != digest:
            raise ValueError()
    backup = PRIVATE/('enrolment-files-'+revision[:8])
    backup.mkdir(mode=0o700)  # Exact revision retries cannot overwrite rollback evidence.
    for name in files:
        target = BASE/name
        if target.exists():
            saved = backup/name
            saved.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
            shutil.copyfile(target, saved)
            saved.chmod(0o600)
    (backup/'manifest.json').write_text(json.dumps(manifest, indent=2)+'\n')
    (backup/'manifest.json').chmod(0o600)
    for name in files:
        target = BASE/name
        target.parent.mkdir(parents=True, exist_ok=True)
        temporary = target.with_name(target.name+'.enrolment-new')
        with temporary.open('xb') as output:
            output.write((stage/name).read_bytes())
        temporary.chmod(0o644)
        os.replace(temporary, target)
    print('Hash-bound application files installed; private rollback copies: '+str(backup))
    print('No migrations, service changes, keys, accounts or routing changes performed.')
except Exception:
    print('Enrolment payload blocked or incomplete; inspect private staging/rollback state.', file=sys.stderr)
    sys.exit(1)
