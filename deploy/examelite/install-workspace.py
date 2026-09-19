#!/usr/bin/env python3
"""Run explicitly as root on the server; no migrations or service restarts here."""
import os, pathlib, pwd, shutil, subprocess, datetime
from workspace_install import add_provider, add_navigation, fix_exam_creation_validation, allow_scoped_language_controller, add_translated_model_answer, require_pdf_images, refresh_pdf_image_cache, protect_pdf_worker_lookup, protect_translation_inputs, isolate_subjective_upload_names

if os.geteuid()!=0: raise SystemExit('Run as root.')
root=pathlib.Path('/home/examelite/public_html')
source=pathlib.Path(__file__).resolve().parent
owner=pwd.getpwnam('examelite')
targets={
 'Tech4LearnCentralLanguageController.php':'app/Http/Controllers/Tech4LearnCentralLanguageController.php',
 'Tech4LearnExamDocuments.php':'app/Services/Tech4LearnExamDocuments.php',
 'Tech4LearnExamTranslations.php':'app/Services/Tech4LearnExamTranslations.php',
 'Tech4LearnTranslationEdits.php':'app/Services/Tech4LearnTranslationEdits.php',
 'Tech4LearnPlanAssignment.php':'app/Services/Tech4LearnPlanAssignment.php',
 'Tech4LearnPlanEditor.php':'app/Services/Tech4LearnPlanEditor.php',
 'Tech4LearnTranslationController.php':'app/Http/Controllers/Tech4LearnTranslationController.php',
 'Tech4LearnDocumentController.php':'app/Http/Controllers/Tech4LearnDocumentController.php',
 'Tech4LearnResultMarking.php':'app/Services/Tech4LearnResultMarking.php',
 'Tech4LearnResultController.php':'app/Http/Controllers/Tech4LearnResultController.php',
 'Tech4LearnAttemptAnswers.php':'app/Services/Tech4LearnAttemptAnswers.php',
 'Tech4LearnAttemptPayload.php':'app/Services/Tech4LearnAttemptPayload.php',
 'Tech4LearnStudentContext.php':'app/Services/Tech4LearnStudentContext.php',
 'Tech4LearnStudentAttempts.php':'app/Services/Tech4LearnStudentAttempts.php',
 'Tech4LearnQuestionMedia.php':'app/Services/Tech4LearnQuestionMedia.php',
 'Tech4LearnPackageImageUpload.php':'app/Services/Tech4LearnPackageImageUpload.php',
 'Tech4LearnQuestionImageUpload.php':'app/Services/Tech4LearnQuestionImageUpload.php',
 'Tech4LearnProctorEvidence.php':'app/Services/Tech4LearnProctorEvidence.php',
 'Tech4LearnAttemptClock.php':'app/Services/Tech4LearnAttemptClock.php',
 'Tech4LearnProctorController.php':'app/Http/Controllers/Tech4LearnProctorController.php',
 'Tech4LearnStudentController.php':'app/Http/Controllers/Tech4LearnStudentController.php',
 'Tech4LearnAuthoringController.php':'app/Http/Controllers/Tech4LearnAuthoringController.php',
 'Tech4LearnQuestionAuthoring.php':'app/Services/Tech4LearnQuestionAuthoring.php',
 'Tech4LearnContentController.php':'app/Http/Controllers/Tech4LearnContentController.php',
 'Tech4LearnWorkspaceController.php':'app/Http/Controllers/Tech4LearnWorkspaceController.php',
 'Tech4LearnNativeController.php':'app/Http/Controllers/Tech4LearnNativeController.php',
 'Tech4LearnLibraryController.php':'app/Http/Controllers/Tech4LearnLibraryController.php',
 'Tech4LearnContentCopies.php':'app/Services/Tech4LearnContentCopies.php',
 'Tech4LearnLaunchTickets.php':'app/Services/Tech4LearnLaunchTickets.php',
 'Tech4LearnWorkspacePolicy.php':'app/Support/Tech4LearnWorkspacePolicy.php',
 'Tech4LearnWorkspaceContext.php':'app/Http/Middleware/Tech4LearnWorkspaceContext.php',
 'Tech4LearnWorkspaceGate.php':'app/Http/Middleware/Tech4LearnWorkspaceGate.php',
 'Tech4LearnWorkspaceProvider.php':'app/Providers/Tech4LearnWorkspaceProvider.php',
 'tech4learn-workspace.php':'routes/tech4learn-workspace.php',
 'launch.blade.php':'resources/views/tech4learn/launch.blade.php',
 'library.blade.php':'resources/views/tech4learn/library.blade.php',
 'navigation.blade.php':'resources/views/tech4learn/navigation.blade.php',
}
for src,dest in targets.items():
    if (root/dest).is_symlink(): raise SystemExit('Refusing symlink target: '+dest)
    subprocess.run(['php','-l',str(source/src)],check=True)
app=root/'config/app.php';layout=root/'resources/views/layouts/master.blade.php';exam=root/'app/Http/Controllers/ExamController.php';language=root/'app/Http/Controllers/LanguageController.php';question_language=root/'app/Http/Controllers/QuestionLangController.php'
renderer=root/'scripts/render-exam-pdf.mjs'
pdf_cache=root/'app/Services/ExamPdfCacheService.php'
pdf_job=root/'app/Jobs/GenerateExamPdfJob.php'
translation=root/'app/Services/ExamTranslationService.php'
subjective_upload=root/'app/Http/Controllers/SubjectiveUploadController.php'
for p in (app,layout,exam,language,question_language,renderer,pdf_cache,pdf_job,translation,subjective_upload):
    if not p.is_file() or p.is_symlink(): raise SystemExit('Expected regular source: '+str(p))
updates={app:add_provider(app.read_text()),layout:add_navigation(layout.read_text()),exam:fix_exam_creation_validation(exam.read_text()),language:allow_scoped_language_controller(language.read_text()),question_language:add_translated_model_answer(question_language.read_text())}
updates[renderer]=require_pdf_images(renderer.read_text())
updates[pdf_cache]=refresh_pdf_image_cache(pdf_cache.read_text())
updates[pdf_job]=protect_pdf_worker_lookup(pdf_job.read_text())
updates[translation]=protect_translation_inputs(translation.read_text())
updates[subjective_upload]=isolate_subjective_upload_names(subjective_upload.read_text())
backup=pathlib.Path('/root/tech4learn-backups')/('native-workspace-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%f'))
backup.mkdir(parents=True,mode=0o700)
originals={}
for dest in [root/d for d in targets.values()]+list(updates):
    originals[dest]=dest.read_bytes() if dest.exists() else None
    if dest.exists():
        saved=backup/dest.relative_to(root);saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(dest,saved)
try:
    for src,relative in targets.items():
        dest=root/relative
        if not dest.parent.exists():
            dest.parent.mkdir(parents=True,exist_ok=True)
            os.chown(dest.parent,owner.pw_uid,owner.pw_gid);os.chmod(dest.parent,0o755)
        shutil.copyfile(source/src,dest);os.chown(dest,owner.pw_uid,owner.pw_gid);os.chmod(dest,0o644)
    for dest,content in updates.items(): dest.write_text(content)
    subprocess.run(['php','-l',str(app)],check=True)
    subprocess.run(['php','-l',str(exam)],check=True)
    subprocess.run(['php','-l',str(language)],check=True)
    subprocess.run(['node','--check',str(renderer)],check=True)
    subprocess.run(['php','-l',str(pdf_cache)],check=True)
    subprocess.run(['php','-l',str(pdf_job)],check=True)
    subprocess.run(['php','-l',str(translation)],check=True)
except BaseException:
    for dest,content in originals.items():
        if content is None: dest.unlink(missing_ok=True)
        else: dest.write_bytes(content)
    raise
print('Native workspace source installed. Backup:',backup)
print('Apply the explicit workspace migration, then clear Laravel caches as examelite.')
