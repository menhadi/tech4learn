<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ManualExamPaperContractTest extends TestCase
{
    public function test_exam_module_exposes_no_ai_full_paper_draft_workflow(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/web.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ExamController.php');
        $service = file_get_contents(__DIR__.'/../../app/Services/ManualExamPaperService.php');
        $index = file_get_contents(__DIR__.'/../../resources/views/exams/index.blade.php');
        $examForm = file_get_contents(__DIR__.'/../../resources/views/exams/action.blade.php');
        $manualQuestion = file_get_contents(__DIR__.'/../../resources/views/exams/paper-question-draft.blade.php');
        $paperEditor = file_get_contents(__DIR__.'/../../resources/views/exams/paper-editor.blade.php');
        $paperEditorField = file_get_contents(__DIR__.'/../../resources/views/exams/partials/paper-editor-field.blade.php');
        $continuousCropper = file_get_contents(__DIR__.'/../../resources/views/question-drafts/continuous-cropper-script.blade.php');
        $auditShow = file_get_contents(__DIR__.'/../../resources/views/exam-quality/show.blade.php');

        $this->assertStringContainsString("name('exams.paper.preview')", $routes);
        $this->assertStringContainsString("name('exams.paper.edit')", $routes);
        $this->assertStringContainsString("name('exams.paper.questions.crop')", $routes);
        $this->assertStringContainsString("name('exams.paper.questions.remove-image')", $routes);
        $this->assertStringContainsString("name('exams.paper.publish')", $routes);
        $this->assertStringContainsString('public function editPaper(', $controller);
        $this->assertStringContainsString('public function cropPaperQuestion(', $controller);
        $this->assertStringContainsString('public function removePaperQuestionImage(', $controller);
        $this->assertStringContainsString("'no_ai_calls' => true", $service);
        $this->assertStringContainsString('QuestionVersion', file_get_contents(__DIR__.'/../../app/Services/QuestionRepairService.php'));
        $this->assertStringContainsString('Edit complete paper', $index);
        $this->assertStringContainsString('View full paper', $examForm);
        $this->assertStringContainsString('Edit full paper', $examForm);
        $this->assertStringContainsString('Edit full paper', $auditShow);
        $this->assertStringContainsString('manual-paper-workspace', $manualQuestion);
        $this->assertStringContainsString('data-crop-into', $manualQuestion);
        $this->assertStringContainsString("view('exams.paper-editor'", $controller);
        $this->assertStringContainsString('paper-question-form', $paperEditor);
        $this->assertStringContainsString('Publish all saved drafts', $paperEditor);
        $this->assertStringContainsString('full-paper-question-pane', $paperEditor);
        $this->assertStringContainsString('Crops auto-save immediately', $paperEditor);
        $this->assertStringContainsString('data-editor-selector', $paperEditorField);
        $this->assertStringContainsString('data-remove-image', $paperEditorField);
        $this->assertStringContainsString('Remove image', $paperEditorField);
        $this->assertStringContainsString("'cropContinuous' => true", $paperEditor);
        $this->assertStringContainsString("'cropExternalTargets' => true", $paperEditor);
        $this->assertStringContainsString('active-crop-target', $paperEditor);
        $this->assertStringContainsString('continuous-source-pages', $continuousCropper);
        $this->assertStringContainsString('data-pdf-page', $continuousCropper);
        $this->assertStringNotContainsString('stage.scrollTo({', $continuousCropper);
        $this->assertStringContainsString('form.requestSubmit()', $continuousCropper);
        $this->assertStringContainsString('saving=true', $continuousCropper);
    }

    public function test_manual_crop_auto_saves_and_supports_detected_pages_and_backgrounds(): void
    {
        $cropper = file_get_contents(__DIR__.'/../../resources/views/question-drafts/manual-cropper.blade.php');
        $repairService = file_get_contents(__DIR__.'/../../app/Services/QuestionRepairService.php');
        $cropScript = file_get_contents(__DIR__.'/../../scripts/extract-pdf-region.py');

        $this->assertStringContainsString('crop-page-jump', $cropper);
        $this->assertStringContainsString('Crop & auto-save', $cropper);
        $this->assertMatchesRegularExpression("/headers\\s*:\\s*\\{\\s*'Accept'\\s*:\\s*'application\\/json'/", $cropper);
        $this->assertStringContainsString("'manual-crop-saved'", $cropper);
        $this->assertStringContainsString('manual-crop-target', $cropper);
        $this->assertStringContainsString('activeEditorSelector', $cropper);
        $this->assertStringContainsString('event.detail?.cropAction', $cropper);
        $this->assertStringContainsString("stage.addEventListener('wheel'", $cropper);
        $this->assertStringContainsString('Scroll the complete PDF below', $cropper);
        $this->assertStringContainsString('background:transparent', $cropper);
        $this->assertStringNotContainsString('bg-warning bg-opacity-25', $cropper);
        $this->assertStringContainsString('public function detectedSourcePage(', $repairService);
        $this->assertStringContainsString('background_mode', $cropScript);
        $this->assertStringContainsString('np.dstack((foreground, alpha))', $cropScript);
    }

    public function test_manual_sessions_do_not_consume_ai_audit_quota(): void
    {
        $access = file_get_contents(__DIR__.'/../../app/Support/SaasAccess.php');
        $auditController = file_get_contents(__DIR__.'/../../app/Http/Controllers/ExamQualityAuditController.php');

        $this->assertStringContainsString("options->manual_editor", $access);
        $this->assertStringContainsString("audits.options->manual_editor", $access);
        $this->assertStringContainsString("options->manual_editor", $auditController);
    }
}
