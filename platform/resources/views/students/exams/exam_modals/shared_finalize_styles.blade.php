<style>
    #finalizeExamModal .exam-finalize-dialog {
        max-width: min(720px, 92vw);
    }
    #finalizeExamModal .exam-finalize-modal {
        border: 1px solid var(--el-border, #d7e2df);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
    }
    #finalizeExamModal .modal-header {
        background: var(--el-primary-soft, rgba(15, 118, 110, .12));
        border-bottom: 1px solid var(--el-border, #d7e2df);
        padding: 14px 24px;
    }
    #finalizeExamModal .modal-title {
        color: var(--el-heading, #111827);
        font-size: 1.2rem;
        font-weight: 800;
    }
    #finalizeExamModal .modal-body {
        max-height: 50vh;
        overflow-y: auto;
        padding: 22px 24px 14px;
        color: var(--el-heading, #111827);
    }
    #finalizeExamModal .modal-body p {
        font-size: 1rem;
        margin-bottom: 14px;
    }
    #finalizeExamModal .exam-finalize-summary {
        background: var(--el-primary-soft, rgba(15, 118, 110, .12));
        border: 1px solid color-mix(in srgb, var(--el-primary, #0f766e) 18%, var(--el-border, #d7e2df));
        border-radius: 14px;
        padding: 16px;
    }
    #finalizeExamModal .exam-finalize-summary strong {
        display: block;
        color: var(--el-heading, #111827);
        margin-bottom: 12px;
    }
    #finalizeExamModal .exam-finalize-stat {
        background: #fff;
        border: 1px solid var(--el-border, #d7e2df);
        border-radius: 12px;
        color: var(--el-heading, #111827);
        gap: 12px;
        min-height: 52px;
        padding: 10px 14px;
    }
    #finalizeExamModal .exam-finalize-stat .badge {
        background: var(--el-primary, #0f766e);
        color: var(--theme-button-text, #fff) !important;
        min-width: 30px;
        padding: 7px 9px;
        position: static !important;
    }
    #finalizeExamModal .exam-finalize-stat img {
        height: 22px;
        width: 22px;
        object-fit: contain;
    }
    #finalizeExamModal .exam-finalize-actions {
        border-top: 1px solid var(--el-border, #d7e2df);
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 24px 20px;
    }
    #finalizeExamModal .exam-modal-primary,
    #finalizeExamModal .exam-modal-primary:hover,
    #finalizeExamModal .exam-modal-primary:focus {
        background: var(--el-primary, #0f766e) !important;
        border-color: var(--el-primary, #0f766e) !important;
        color: var(--theme-button-text, #fff) !important;
        font-weight: 800;
    }
    #finalizeExamModal .exam-modal-secondary,
    #finalizeExamModal .exam-modal-secondary:hover,
    #finalizeExamModal .exam-modal-secondary:focus {
        background: var(--el-secondary, #f59e0b) !important;
        border-color: var(--el-secondary, #f59e0b) !important;
        color: var(--theme-button-text, #fff) !important;
        font-weight: 800;
    }
    @media (max-width: 767.98px) {
        #finalizeExamModal .exam-finalize-dialog {
            max-width: 94vw;
        }
        #finalizeExamModal .exam-finalize-actions {
            align-items: stretch;
            flex-direction: column;
        }
        #finalizeExamModal .exam-finalize-actions .btn {
            width: 100%;
        }
    }
</style>
